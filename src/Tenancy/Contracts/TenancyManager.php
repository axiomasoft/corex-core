<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use Closure;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;
use CoreX\Tenancy\WorkspaceRef;

/**
 * Single point of tenant-context switching (connection/cache/queue/fs).
 * Boxed installs bind a null-manager (constant context, no stancl); cloud
 * installs bind реализацию из пакета corex/tenancy — the choice is a container
 * binding, never an `if(cloud)` branch.
 *
 * @internal spec: B-11 §3.1, D10
 */
interface TenancyManager
{
    /**
     * The active context, or null when running in the central/root scope
     * (not inside any tenant).
     */
    public function context(): ?TenantContext;

    public function initialized(): bool;

    public function initialize(AccountRef $account): void;

    /**
     * Narrows (or clears, with null) the workspace within the already-
     * initialized account context. No-op on the boxed null-manager.
     */
    public function setWorkspace(?WorkspaceRef $workspace): void;

    public function end(): void;

    /**
     * Runs `$callback` inside the given account/workspace context, then
     * unconditionally reverts (finally) — the seam for control-plane sweeps
     * and cron jobs run outside a request cycle.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed;
}
