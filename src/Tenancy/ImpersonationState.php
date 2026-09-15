<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

use DateTimeImmutable;

/**
 * Snapshot of an active impersonation session — resolved from the claimed
 * `root_impersonation_grants` row (D143), never re-derived from the id_token.
 *
 * @internal spec: B-11 §5.9, D140
 */
final readonly class ImpersonationState
{
    public function __construct(
        public string $grantId,
        public string $staffIdentityId,
        public string $targetUserId,
        public DateTimeImmutable $expiresAt,
    ) {}
}
