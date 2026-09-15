<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

/**
 * The current account/workspace pair a request or job runs under. Resolved
 * by corex/tenancy; boxed installs get a constant singleton via
 * {@see SingleAccountResolver}.
 *
 * @internal spec: B-10 §3.1, B-11 §3.1 (owner: B-11)
 */
final readonly class TenantContext
{
    public function __construct(
        public AccountRef $account,
        public ?WorkspaceRef $workspace = null,
        private bool $boxed = false,
    ) {}

    /**
     * True iff this context was produced by {@see SingleAccountResolver}
     * (boxed installs), not by a real P2 tenancy resolver.
     */
    public function isBoxed(): bool
    {
        return $this->boxed;
    }

    public function forWorkspace(WorkspaceRef $workspace): self
    {
        return new self($this->account, $workspace, $this->boxed);
    }
}
