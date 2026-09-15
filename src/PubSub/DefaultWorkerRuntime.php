<?php

declare(strict_types=1);

namespace CoreX\PubSub;

use CoreX\Contracts\WorkerRuntime;
use Override;

/**
 * Production `WorkerRuntime`: Octane detection via `app()->bound(...)` on the
 * Octane facade class-string (works even without `laravel/octane` installed —
 * `::class` resolves to a literal FQCN string at compile time, no autoload)
 * plus the `LARAVEL_OCTANE` env var Octane's server process exports to every
 * worker it spawns.
 *
 * @internal spec: Implementation Rules, P1.24
 */
final class DefaultWorkerRuntime implements WorkerRuntime
{
    #[Override]
    public function isOctane(): bool
    {
        // getenv(), not env(): env() is banned outside config/ (returns null
        // once config is cached) — same seam CoreXTestCase uses for env reads
        // in application code.
        return app()->bound('Laravel\Octane\Octane') || getenv('LARAVEL_OCTANE') !== false;
    }

    #[Override]
    public function runningInConsole(): bool
    {
        return app()->runningInConsole();
    }
}
