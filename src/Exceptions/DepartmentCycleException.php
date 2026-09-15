<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use RuntimeException;

/**
 * Move-guard: the new parent must not be inside the moving department's own
 * subtree (`new_parent.path <@ moving.path`).
 *
 * @internal spec: B-10 §5.7
 */
final class DepartmentCycleException extends RuntimeException
{
    public function __construct(string $departmentId, string $newParentId)
    {
        parent::__construct(sprintf(
            'Cannot move department %s under %s — the new parent is inside the moving subtree (B-10 §5.7).',
            $departmentId,
            $newParentId,
        ));
    }
}
