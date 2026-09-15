<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

/**
 * Immutable reference to a workspace: identity, slug, type, and its
 * position (path/parent) in the workspace hierarchy.
 *
 * @internal spec: B-10 §3.1, B-11 §3.1 (owner: B-11)
 */
final readonly class WorkspaceRef
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $type,
        public string $path,
        public ?string $parentId,
    ) {}
}
