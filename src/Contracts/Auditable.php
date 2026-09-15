<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Marks a model as auditable. Pair with the HasAuditLog trait, which records
 * an append-only change history through the AuditLogger.
 */
interface Auditable
{
    /**
     * Attribute names excluded from the audit diff.
     *
     * @return list<string>
     */
    public function auditExcept(): array;

    /**
     * Attribute names masked in the audit diff.
     *
     * @return list<string>
     */
    public function auditSensitive(): array;
}
