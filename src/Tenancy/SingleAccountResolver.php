<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

use CoreX\Tenancy\Contracts\TenantContextResolver;

/**
 * Boxed-install stub: a deterministic constant TenantContext, no config
 * lookup, no workspace. Real resolvers (host/root, corex/tenancy) land
 * later.
 *
 * @internal spec: D10
 */
final class SingleAccountResolver implements TenantContextResolver
{
    private const ACCOUNT_ID = '00000000-0000-7000-8000-000000000000';

    private const ACCOUNT_SLUG = 'boxed';

    private const ACCOUNT_STATUS = 'active';

    public function current(): TenantContext
    {
        return new TenantContext(
            account: new AccountRef(
                id: self::ACCOUNT_ID,
                slug: self::ACCOUNT_SLUG,
                status: self::ACCOUNT_STATUS,
                features: [],
                limits: [],
            ),
            workspace: null,
            boxed: true,
        );
    }
}
