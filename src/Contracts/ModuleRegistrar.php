<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Lightweight module loader: a module's top-level service provider registers
 * its child providers here. Top-level providers are still discovered via
 * Laravel package auto-discovery — no external dependency (own loader).
 *
 * Registration is ADDITIVE for the lifetime of the process: once a
 * provider is registered, the container carries its bindings until the
 * process exits — there is no `unregister()`. Turning a module off at
 * runtime therefore has exactly one canonical path: flip its state through
 * corex/modules's module lifecycle FSM (`enabled` → `disabled`), which is a
 * data/routing decision (does this tenant see the module), and then restart
 * any long-running worker (queue, Octane) so its container is rebuilt
 * without the disabled module's providers. A worker that keeps running with
 * a provider still registered keeps serving that module's routes/listeners
 * for requests already in flight — by design, not a bug to work around here.
 */
interface ModuleRegistrar
{
    /** @param class-string ...$providers */
    public function register(string ...$providers): void;

    /** @return list<class-string> */
    public function registered(): array;
}
