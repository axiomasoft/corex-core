<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

use CoreX\Tenancy\Contracts\ImpersonationService;
use LogicException;
use Override;

/**
 * Boxed-install default: impersonation does not exist there at all (B-11
 * §6.1 "Impersonation | IdP-грант | отсутствует"). `stop()`/`active()`/
 * `state()` are safe no-ops so ambient callers (CurrentActor) never branch
 * on the install profile; `start()` throws — there is no grant to claim.
 *
 * @internal spec: B-11 §6.1, D10
 */
final class NullImpersonationService implements ImpersonationService
{
    #[Override]
    public function start(string $grantId, string $staffIdentityId): void
    {
        throw new LogicException('Impersonation is not available on a boxed install (no root_impersonation_grants).');
    }

    #[Override]
    public function stop(): void
    {
        // no-op — nothing was ever active.
    }

    #[Override]
    public function active(): ?string
    {
        return null;
    }

    #[Override]
    public function state(): ?ImpersonationState
    {
        return null;
    }
}
