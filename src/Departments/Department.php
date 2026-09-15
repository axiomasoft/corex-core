<?php

declare(strict_types=1);

namespace CoreX\Departments;

/**
 * Read-model returned by `DepartmentRepository` implementations.
 *
 * @internal spec: B-10 §3.1
 */
final readonly class Department
{
    public function __construct(
        public string $id,
        public string $workspaceId,
        public ?string $parentId,
        public string $path,
        public string $name,
        public ?string $headUserId,
        public int $sortOrder = 0,
    ) {}
}
