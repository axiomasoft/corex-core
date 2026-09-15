<?php

declare(strict_types=1);

namespace CoreX\Events;

/**
 * Opt-in for pre-action `DomainEvent`s a listener can abort (Architecture_CoreX
 * §7.3, e.g. a `ProductCreating`-style hook). Kept separate from `DomainEvent`
 * itself — most domain events are informational and never cancellable.
 */
interface Cancellable
{
    public function cancel(): void;

    public function isCancelled(): bool;
}
