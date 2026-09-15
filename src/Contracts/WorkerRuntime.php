<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Detects whether the current process is eligible to call
 * `PubSub::subscribe()`. `Application::runningInConsole()` alone is NOT
 * sufficient: an Octane worker boots once via `php artisan octane:start` and
 * then serves HTTP requests through that same long-running CLI process, so
 * `runningInConsole()` still reports `true` for every request it handles —
 * a guard built on it alone is a no-op under Octane: `subscribe()` called
 * from an HTTP handler would pass the check and block that worker forever.
 *
 * This seam exists so a test can fake "running under Octane" without the
 * `laravel/octane` package installed (it is not a require of this package)
 * — implement this interface with a small test double and bind it over the
 * container's default.
 *
 * @internal spec: B-10 §3.1
 */
interface WorkerRuntime
{
    /**
     * True when the process runs inside an Octane server (long-running,
     * request-multiplexing) — `runningInConsole()` cannot be trusted to
     * distinguish an HTTP request from a worker command in that case.
     */
    public function isOctane(): bool;

    /** Mirrors `Application::runningInConsole()` — the CLI-vs-HTTP SAPI check. */
    public function runningInConsole(): bool;
}
