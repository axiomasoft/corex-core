<?php

declare(strict_types=1);

namespace CoreX\PubSub;

use Closure;
use CoreX\Contracts\PubSub;
use CoreX\Contracts\WorkerRuntime;
use CoreX\Support\Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * Boxed fallback driver for installs without Redis — polls
 * `sys_pubsub_messages` by an auto-increment cursor instead of `LISTEN`/
 * `NOTIFY` (forbidden under PgBouncer transaction pooling, R-15). `id` is a
 * plain bigint (not UUIDv7/ADR-003) — this table is an internal delivery
 * cursor, not a domain entity.
 *
 * DURABLE cursor: the poll position for a `($channel,
 * $subscriber)` pair is persisted in `sys_pubsub_cursors`, not restarted at 0
 * or at `max(id)` on every `subscribe()` call. A rolling-restart of the
 * subscriber process resumes exactly where the previous process left off —
 * this driver backs the RBAC/module-activity invalidation channel, and a
 * "start from max(id) on every subscribe()" design would silently DROP
 * every message published during the restart window (stale cache, not a
 * storm). The corresponding row in `sys_pubsub_cursors` is created only the
 * FIRST time a given `$subscriber` name is seen for a channel: at that point
 * there is no prior position to resume from, so it starts at the channel's
 * current `max(id)` (skips pre-existing backlog) unless `$replayBacklog` is
 * passed explicitly — never a default.
 *
 * `$connection` mirrors the other PG-only drivers' seam (null = app
 * default). `$maxPolls` is test-only: `null` polls forever (real usage), a
 * finite count lets tests observe delivery without blocking — it does NOT
 * bypass the worker-context guard (SubscribeGuard runs regardless).
 *
 * @internal spec: B-10 §6.1, D44, P1.24, Implementation Rules, pre-mortem C7
 */
final class DatabasePollPubSub implements PubSub
{
    public function __construct(
        private readonly ?string $connection = null,
        private readonly ?int $pollIntervalMicroseconds = null,
        private readonly WorkerRuntime $runtime = new DefaultWorkerRuntime,
    ) {}

    #[Override]
    public function publish(string $channel, array $payload): void
    {
        $connection = DB::connection($this->connection);

        $connection->transaction(function () use ($channel, $payload, $connection): void {
            $connection->select(
                query: 'SELECT pg_advisory_xact_lock(hashtext(?))',
                bindings: ['corex-pubsub:'.$channel],
            );

            $connection->table(Config::pubsubMessagesTable())->insert([
                'channel' => $channel,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * @param  Closure(array<string, mixed>): void  $handler
     * @param  int|null  $maxPolls  test-only: stop after N poll iterations instead of polling forever.
     * @param  string  $subscriber  durable-cursor identity — a distinct name per logical
     *                              subscriber lets several independent consumers each track their own
     *                              position on the same channel.
     * @param  bool  $replayBacklog  only meaningful the first time this `$subscriber` subscribes to
     *                               `$channel` (no persisted cursor yet): `true` starts at the beginning
     *                               of the channel's history instead of the current `max(id)`. Never
     *                               defaults to `true`.
     *
     * @internal spec: D44, Implementation Rules, P1.24
     */
    public function subscribe(
        string $channel,
        Closure $handler,
        ?int $maxPolls = null,
        string $subscriber = 'default',
        bool $replayBacklog = false,
    ): void {
        SubscribeGuard::assert($this->runtime, Config::pubsubOctaneWorkerOptIn(), self::class);

        $connection = DB::connection($this->connection);
        $messagesTable = Config::pubsubMessagesTable();
        $cursorsTable = Config::pubsubCursorsTable();

        $cursor = $this->loadOrBootstrapCursor($connection, $cursorsTable, $messagesTable, $channel, $subscriber, $replayBacklog);
        $polls = 0;

        while ($maxPolls === null || $polls < $maxPolls) {
            $rows = $connection->table($messagesTable)
                ->where('channel', $channel)
                ->where('id', '>', $cursor)
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                $cursor = (int) $row->id;
                /** @var array<string, mixed> $payload */
                $payload = json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR);
                $handler($payload);

                $this->persistCursor($connection, $cursorsTable, $channel, $subscriber, $cursor);
            }

            $polls++;

            if ($maxPolls === null || $polls < $maxPolls) {
                usleep($this->pollIntervalMicroseconds ?? Config::pubsubPollIntervalMicroseconds());
            }
        }
    }

    private function loadOrBootstrapCursor(
        ConnectionInterface $connection,
        string $cursorsTable,
        string $messagesTable,
        string $channel,
        string $subscriber,
        bool $replayBacklog,
    ): int {
        $existing = $connection->table($cursorsTable)
            ->where('channel', $channel)
            ->where('subscriber', $subscriber)
            ->first();

        if ($existing !== null) {
            return (int) $existing->position;
        }

        $start = $replayBacklog
            ? 0
            : (int) ($connection->table($messagesTable)->where('channel', $channel)->max('id') ?? 0);

        $this->persistCursor($connection, $cursorsTable, $channel, $subscriber, $start);

        return $start;
    }

    private function persistCursor(
        ConnectionInterface $connection,
        string $cursorsTable,
        string $channel,
        string $subscriber,
        int $position,
    ): void {
        $connection->table($cursorsTable)->updateOrInsert(
            ['channel' => $channel, 'subscriber' => $subscriber],
            ['position' => $position, 'updated_at' => now()],
        );
    }
}
