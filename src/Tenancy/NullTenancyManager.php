<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

use Closure;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;

/**
 * Boxed-install default: a single constant account context, no stancl
 * bootstrap, `initialized()` always true. Cloud installs (`corex/tenancy`
 * present) rebind {@see TenancyManager} to `StanclTenancyManager` — same
 * pattern as the module-activation-gate boxed/cloud seam.
 *
 * @internal spec: D10, B-11 §6.1 L892–907
 */
final class NullTenancyManager implements TenancyManager
{
    public function __construct(
        private readonly TenantContextResolver $resolver,
    ) {}

    public function context(): TenantContext
    {
        return $this->resolver->current();
    }

    public function initialized(): bool
    {
        return true;
    }

    public function initialize(AccountRef $account): void
    {
        // no-op — boxed installs run a single constant account.
    }

    public function setWorkspace(?WorkspaceRef $workspace): void
    {
        // no-op — boxed installs have no workspace tier.
    }

    public function end(): void
    {
        // no-op.
    }

    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed
    {
        return $callback();
    }
}
