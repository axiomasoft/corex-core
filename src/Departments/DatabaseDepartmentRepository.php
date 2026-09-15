<?php

declare(strict_types=1);

namespace CoreX\Departments;

use CoreX\Contracts\DepartmentRepository;
use CoreX\Events\DepartmentMoved;
use CoreX\Exceptions\CrossWorkspaceMoveException;
use CoreX\Exceptions\DepartmentCycleException;
use CoreX\Models\Department as DepartmentModel;
use CoreX\Support\Config;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Override;
use RuntimeException;

/**
 * `sys_departments`/`sys_department_user` on ltree.
 *
 * `$connection` mirrors DatabaseSettingsRepository's/DatabaseFeatureFlags'
 * seam (null = app default), used by the PG-only test lane — ltree/GIST
 * have no sqlite equivalent.
 *
 * @internal spec: B-10 §2/§3.1/§5.7, D12
 */
final class DatabaseDepartmentRepository implements DepartmentRepository
{
    public function __construct(
        private readonly ?string $connection = null,
    ) {}

    #[Override]
    public function find(string $id): ?Department
    {
        $row = DepartmentModel::on($this->connection)->where('id', $id)->first();

        return $row instanceof DepartmentModel ? $this->toDto($row) : null;
    }

    #[Override]
    public function subtree(string $departmentId): array
    {
        $root = DepartmentModel::on($this->connection)->where('id', $departmentId)->first();

        if (! $root instanceof DepartmentModel) {
            return [];
        }

        /** @var list<DepartmentModel> $rows */
        $rows = DepartmentModel::on($this->connection)
            ->where('workspace_id', $root->workspace_id)
            ->whereRaw('path <@ ?::ltree', [$root->path])
            ->orderBy('path')
            ->get()
            ->all();

        return array_map($this->toDto(...), $rows);
    }

    #[Override]
    public function primaryOf(string $userId): ?Department
    {
        $departmentId = $this->db()->table(Config::departmentUserTable())
            ->where('user_id', $userId)
            ->where('is_primary', true)
            ->value('department_id');

        return is_string($departmentId) ? $this->find($departmentId) : null;
    }

    #[Override]
    public function move(string $departmentId, ?string $newParentId): void
    {
        $this->db()->transaction(function () use ($departmentId, $newParentId): void {
            // (1) Minimal read for the LOCK KEY only. workspace_id is immutable,
            //     so it can be read before the lock; the raw (soft-delete-blind)
            //     query still returns it for a row mid-deletion, so we always
            //     serialize on the right workspace. Absent row → explicit throw,
            //     never a silent no-op (A5 If-then).
            $lockWorkspaceId = $this->db()->table(Config::departmentsTable())
                ->where('id', $departmentId)
                ->value('workspace_id');

            if (! is_string($lockWorkspaceId)) {
                throw (new ModelNotFoundException)->setModel(DepartmentModel::class, [$departmentId]);
            }

            // (2) Advisory lock BEFORE reading the mutable path (A5). Xact-scoped,
            //     released on commit — no session-level lock.
            $this->db()->statement('select pg_advisory_xact_lock(hashtext(?))', ['sys_departments:'.$lockWorkspaceId]);

            // (3) RE-READ the moving row UNDER the lock — fresh path/parent_id.
            //     Eloquent (SoftDeletes) excludes a concurrently soft-deleted row,
            //     so firstOrFail() throws instead of running a no-op UPDATE that
            //     matches nothing (A5/A24 If-then). All downstream maths use THIS
            //     row, never the pre-lock read.
            /** @var DepartmentModel $moving */
            $moving = DepartmentModel::on($this->connection)->where('id', $departmentId)->firstOrFail();

            // The lock key was read before the lock; workspace_id is immutable,
            // but if it changed under us a concurrent writer is doing something
            // the contract forbids — fail loudly rather than lock the wrong key.
            if ($moving->workspace_id !== $lockWorkspaceId) {
                throw new RuntimeException("Department {$departmentId} changed workspace concurrently during move().");
            }

            $newParentPath = '';

            if ($newParentId !== null) {
                /** @var DepartmentModel $newParent */
                $newParent = DepartmentModel::on($this->connection)->where('id', $newParentId)->firstOrFail();

                // Parent-side workspace guard, symmetric to the descendant-side
                // filter on the UPDATE below: the new parent
                // is read UNDER the already-held advisory lock and must share the
                // moving row's workspace. ltree paths are globally unique, so a
                // cross-workspace parent slips past the cycle check and the FK
                // layer — reparenting into another tenant's subtree would be a
                // silent cross-tenant integrity breach, not a legal move.
                if ($newParent->workspace_id !== $lockWorkspaceId) {
                    throw new CrossWorkspaceMoveException($departmentId, $newParentId);
                }

                if ($newParent->path === $moving->path || str_starts_with($newParent->path, $moving->path.'.')) {
                    throw new DepartmentCycleException($departmentId, $newParentId);
                }

                $newParentPath = $newParent->path;
            }

            $oldPath = $moving->path;
            $ownLabel = str_contains($oldPath, '.') ? substr($oldPath, strrpos($oldPath, '.') + 1) : $oldPath;
            $newPath = $newParentPath !== '' ? $newParentPath.'.'.$ownLabel : $ownLabel;

            // ONE UPDATE over the whole subtree (`path <@`) — no recursive
            // per-row traversal: strip the old-parent prefix
            // (nlevel(old moving path) - 1 labels) off every row under the
            // moving path and re-prepend the new parent's path.
            //
            // Scoped to the moving row's workspace: ltree paths are globally
            // unique today, but the contract is per-workspace and
            // subtree()/primaryOf() already filter
            // on workspace_id — a same-shaped path in ANOTHER workspace under
            // `path <@` must never be rewritten by this workspace's move().
            $this->db()->statement(
                'update '.Config::departmentsTable().' set
                    path = (?::ltree || subpath(path, nlevel(?::ltree) - 1)),
                    parent_id = case when id = ? then ? else parent_id end,
                    updated_at = now()
                where path <@ ?::ltree and workspace_id = ?',
                [$newParentPath, $oldPath, $departmentId, $newParentId, $oldPath, $lockWorkspaceId],
            );

            // A6: dispatch AFTER the transaction commits, not inside it — the
            // dept_tree cache invalidator (entity-scoped RBAC) must not
            // read uncommitted paths, and a rollback must not leak the event.
            $this->db()->afterCommit(static fn () => event(
                new DepartmentMoved(departmentId: $departmentId, oldPath: $oldPath, newPath: $newPath),
            ));
        });
    }

    private function toDto(DepartmentModel $row): Department
    {
        return new Department(
            id: $row->id,
            workspaceId: $row->workspace_id,
            parentId: $row->parent_id,
            path: $row->path,
            name: $row->name,
            headUserId: $row->head_user_id,
            sortOrder: $row->sort_order,
        );
    }

    private function db(): Connection
    {
        return DB::connection($this->connection);
    }
}
