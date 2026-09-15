<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use DateTimeImmutable;

/**
 * Retention policy for the append-only audit journal. `olderThan()` returns
 * the cutoff: rows with occurred_at STRICTLY BEFORE it may be pruned by
 * `corex:audit:prune`. `null` means "no boundary" — the boxed-safe default
 * (NullAuditRetention) keeps everything forever.
 *
 * Per-tariff retention numbers (billing) are out of scope here — a host
 * binds its own implementation.
 *
 * @internal spec: B-10 §7.4 п.8
 */
interface AuditRetention
{
    public function olderThan(): ?DateTimeImmutable;
}
