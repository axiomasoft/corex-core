<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use RuntimeException;

/**
 * Move-guard, symmetric to the descendant-side filter: the new parent must
 * live in the SAME workspace as the moving department. ltree paths are
 * globally unique today, so a cross-workspace parent passes the cycle check
 * and the FK layer silently — reparenting a row into another tenant's
 * subtree is a cross-tenant integrity breach, not a legal move.
 *
 * @internal spec: B-10 §5.7, P1.29 M-2, D63, D67
 */
final class CrossWorkspaceMoveException extends RuntimeException
{
    public function __construct(string $departmentId, string $newParentId)
    {
        parent::__construct(sprintf(
            'Cannot move department %s under %s — the new parent belongs to a different workspace (B-10 §5.7 tenant isolation).',
            $departmentId,
            $newParentId,
        ));
    }
}
