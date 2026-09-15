<?php

declare(strict_types=1);

namespace CoreX\PubSub;

use CoreX\Contracts\WorkerRuntime;
use LogicException;

/**
 * Shared `subscribe()` runtime check for the Redis and database-poll
 * drivers. Both `subscribe()` implementations poll/block forever — calling
 * either from an HTTP request cycle hangs that worker. `runningInConsole()`
 * is trustworthy for a plain PHP-FPM/CLI split (Octane is not part of
 * the supported runtime), but an Octane runtime invalidates that check (see
 * `CoreX\Contracts\WorkerRuntime` docblock): under Octane, `subscribe()` is
 * refused unless the caller explicitly opts in via
 * `config('corex.pubsub.octane_worker_opt_in')`, set only by a dedicated
 * worker command — never by request-handling code.
 *
 * @internal spec: P1.24, D44, A29, D16
 */
final class SubscribeGuard
{
    public static function assert(WorkerRuntime $runtime, bool $octaneWorkerOptIn, string $driverClass): void
    {
        if ($runtime->isOctane()) {
            if ($octaneWorkerOptIn) {
                return;
            }

            throw new LogicException(sprintf(
                '%s::subscribe() detected an Octane runtime. Octane workers stay in the same long-running '.
                'CLI process across requests, so runningInConsole() cannot tell an HTTP request from a worker '.
                'command apart (A29) — subscribe() called from an HTTP handler would block that worker forever. '.
                'Call it only from a dedicated worker command that explicitly sets '.
                "config(['corex.pubsub.octane_worker_opt_in' => true]) before subscribing.",
                $driverClass,
            ));
        }

        if (! $runtime->runningInConsole()) {
            throw new LogicException(sprintf(
                '%s::subscribe() polls/blocks forever — call it only from a queue worker or console command, '.
                'never inside an HTTP request cycle (B-10 §3.1).',
                $driverClass,
            ));
        }
    }
}
