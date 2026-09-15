<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use CoreX\Enums\ImpersonationRestrictedAction;
use CoreX\Tenancy\ImpersonationGuard;
use RuntimeException;

/**
 * Thrown by any of the three read-only-invariant layers (D141) when an
 * impersonated session attempts a write the layer does not recognize as
 * explicitly allowed. Named constructors point at the exact escape hatch
 * for the layer that raised it — a bare "write blocked" message would leave
 * the premortem A8/A9 diagnosis (route marker vs `allowing()` scope) to
 * guesswork.
 *
 * @internal spec: B-11 §5.9, D141/D142
 */
final class ImpersonationRestrictedException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /** L2 — `CoreX\Tenancy\Impersonation\ImpersonatedModelWriteGuard` (corex/tenancy; named, not `{@see}`-linked — core must not import it, D91). */
    public static function forModelWrite(string $modelClass): self
    {
        return new self(
            "Model write blocked under impersonation: {$modelClass}. ".
            'Wrap the mutation in ImpersonatedModelWriteGuard::allowing() if it is intentionally allowed.',
        );
    }

    /** L3 — {@see ImpersonationGuard::assertAllowed()}. */
    public static function forAction(ImpersonationRestrictedAction $action): self
    {
        return new self(
            "Action restricted under impersonation: {$action->name} (B-11 §5.9 п.3).",
        );
    }
}
