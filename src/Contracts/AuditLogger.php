<?php

declare(strict_types=1);

namespace CoreX\Contracts;

interface AuditLogger
{
    /**
     * Record an append-only audit entry. The default implementation only
     * enqueues (sync-path budget ≤0.3ms) — the row is written by a queue
     * worker.
     */
    public function record(AuditEntry $entry): void;
}
