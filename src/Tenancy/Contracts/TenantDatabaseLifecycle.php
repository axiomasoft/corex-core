<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\AccountRef;

/**
 * Physical DB-per-account drop/export (B-11 §5.1/§7.3, D13/AC-7). Lives in
 * corex/core so `AccountLifecycle` (core-level FSM consumer) can depend on
 * the seam without importing `Stancl\*`/S3/`pg_dump` — those stay isolated
 * to the corex/tenancy implementation (D13).
 *
 * @internal spec: B-11 §3.2/§7.3, P2.12
 */
interface TenantDatabaseLifecycle
{
    /** pg_dump -Fc + CSV/JSON per entity to S3; returns id of root_account_exports row. */
    public function export(AccountRef $account, string $kind): string;

    /**
     * Physically drops the account's tenant database. Implementations MUST
     * refuse (throw) unless the account's most recent export is `ready`
     * (D13/AC-7, compliance-critical — export-before-DROP invariant).
     */
    public function drop(AccountRef $account): void;
}
