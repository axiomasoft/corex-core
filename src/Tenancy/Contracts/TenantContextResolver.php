<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Tenancy\TenantContext;

/**
 * Resolves the active TenantContext. Real (P2) implementations live in
 * corex/tenancy; boxed installs default to SingleAccountResolver.
 */
interface TenantContextResolver
{
    public function current(): TenantContext;
}
