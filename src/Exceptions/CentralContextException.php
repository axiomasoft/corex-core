<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use CoreX\Tenancy\Contracts\TenantContextResolver;
use RuntimeException;

/**
 * Thrown by {@see TenantContextResolver::current()}
 * cloud implementations when called outside an initialized tenant (root/
 * central scope) — a caller that needs a tenant context asked for one where
 * none exists, e.g. Settings resolution reached from a control-plane
 * request. The boxed `SingleAccountResolver` never throws this (constant
 * context, always "initialized").
 */
final class CentralContextException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No tenant context is active — this call must run inside an initialized tenant (not the central/root scope).');
    }
}
