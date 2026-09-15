<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Audit\CurrentActor;
use CoreX\Tenancy\ImpersonationState;
use CoreX\Tenancy\NullImpersonationService;

/**
 * Support impersonation (B-11 §5.9, AC-31). Boxed installs bind
 * {@see NullImpersonationService} (no grants exist there —
 * B-11 §6.1); cloud installs rebind to `DatabaseImpersonationService`
 * (corex/tenancy, D10). Lives in corex/core (D139) — the sole consumer,
 * {@see CurrentActor}, is ядро and must not import
 * corex/tenancy.
 *
 * Signature extends B-11 §3.3's snippet (D140): `start()` also takes the
 * AUTHENTICATED staff identity (a leaked `grantId` alone cannot be tied to
 * its presenter), and `state()` is additive — `active()` keeps its exact
 * original return type for spec backward-compatibility.
 *
 * @internal spec: B-11 §3.3/§5.9, D139/D140
 */
interface ImpersonationService
{
    /**
     * Claims and activates `$grantId` on behalf of `$staffIdentityId`.
     * Implementations MUST reject reuse, expiry, revocation, non-staff
     * presenters, and grants issued for a different account/tenant — see
     * `DatabaseImpersonationService` for the exact predicate.
     */
    public function start(string $grantId, string $staffIdentityId): void;

    /** Idempotent — a no-op when no impersonation is active. */
    public function stop(): void;

    /** The active grant id, or null outside impersonation. */
    public function active(): ?string;

    /** The active impersonation snapshot, or null outside impersonation. */
    public function state(): ?ImpersonationState;
}
