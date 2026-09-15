<?php

declare(strict_types=1);

namespace CoreX\Audit;

use CoreX\Contracts\AuditRetention;
use DateTimeImmutable;

/**
 * Boxed-safe default: no retention boundary — `corex:audit:prune` deletes
 * nothing. An append-only journal defaults to keeping everything; a host that
 * wants pruning binds its own AuditRetention (config `corex.bindings.audit_retention`).
 */
final class NullAuditRetention implements AuditRetention
{
    public function olderThan(): ?DateTimeImmutable
    {
        return null;
    }
}
