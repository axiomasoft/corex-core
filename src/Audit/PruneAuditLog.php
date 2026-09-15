<?php

declare(strict_types=1);

namespace CoreX\Audit;

use CoreX\Contracts\AuditRetention;
use CoreX\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Prunes sys_audit_log rows older than the bound AuditRetention boundary.
 *
 * This is the ONLY legal DELETE against the append-only audit journal: the
 * append path never deletes, and the observer/logger never remove rows —
 * retention is applied here, deliberately and observably, on a documented
 * schedule the host wires into its scheduler. With the boxed-safe
 * NullAuditRetention (no boundary) it deletes nothing.
 *
 * `--database` targets a specific connection (sys_audit_log is PG-only);
 * omit it for the app default.
 *
 * @internal spec: B-10 §7.4 п.8, D12
 */
final class PruneAuditLog extends Command
{
    protected $signature = 'corex:audit:prune {--database= : Connection to prune (default: the app default)}';

    protected $description = 'Delete audit-log rows older than the configured retention boundary (the only legal DELETE on the append-only journal).';

    public function handle(AuditRetention $retention): int
    {
        $cutoff = $retention->olderThan();

        if ($cutoff === null) {
            $this->info('Audit retention: no boundary configured — nothing pruned.');

            return self::SUCCESS;
        }

        /** @var string|null $connection */
        $connection = $this->option('database');

        $deleted = AuditLog::on($connection)
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        $this->info(sprintf(
            'Audit retention: pruned %d row(s) older than %s.',
            $deleted,
            $cutoff->format(DATE_ATOM),
        ));

        return self::SUCCESS;
    }
}
