<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Departments\Department;
use CoreX\Events\DepartmentMoved;
use CoreX\Exceptions\DepartmentCycleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Department hierarchy on `sys_departments` (ltree path).
 *
 * Soft-deleted departments are excluded from every read here —
 * find()/subtree()/primaryOf() never surface a removed row, so a deleted
 * department drops out of the scope feeding entity-scoped RBAC.
 *
 * @internal spec: B-10 §3.1/§5.7
 */
interface DepartmentRepository
{
    public function find(string $id): ?Department;

    /**
     * The department and everything under it, scoped to the root's workspace
     * (`path <@` includes the root; ltree paths are globally unique but the
     * contract is per-workspace).
     *
     * @return list<Department>
     */
    public function subtree(string $departmentId): array;

    /** The user's primary department (AzGuard scope=dept). */
    public function primaryOf(string $userId): ?Department;

    /**
     * Reparent a department (and its whole subtree) in one `UPDATE ... WHERE
     * path <@` — no recursive traversal. `$newParentId = null` moves it to
     * the workspace root. Serialized per workspace via `pg_advisory_xact_lock`
     * (lock BEFORE the row is re-read under it). {@see DepartmentMoved} is
     * dispatched AFTER the transaction commits.
     *
     * @throws DepartmentCycleException the new parent is inside the moving subtree
     * @throws ModelNotFoundException the department (or new parent) does not exist
     *                                or was soft-deleted — never a silent no-op
     */
    public function move(string $departmentId, ?string $newParentId): void;
}
