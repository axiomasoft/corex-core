<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\ProvisionParams;

/**
 * Creates an account's tenant database (golden-template clone + post-steps).
 * Idempotent — a repeated call for an already-provisioned account is a no-op
 * (replayable pipeline). `drop()`/`export()` are lifecycle operations owned
 * by the `AccountLifecycle`/`TenantDatabaseLifecycle` contracts, not this
 * one.
 *
 * @internal spec: premortem B4, P2.12, Code Guidance P2.3
 */
interface TenantDatabaseProvisioner
{
    public function provision(ProvisionParams $params): void;
}
