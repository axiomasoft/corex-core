<?php

declare(strict_types=1);

namespace CoreX\PubSub;

use Closure;
use CoreX\Contracts\PubSub;
use Override;

/**
 * Sync in-memory driver (tests, boxed-minimum without Redis/DB polling).
 * `subscribe()` just registers a handler and returns immediately — no
 * long-running loop, so it carries none of the request-cycle restriction the
 * other two drivers enforce. `publish()` calls registered handlers inline,
 * in the same process, in registration order.
 */
final class NullPubSub implements PubSub
{
    /** @var array<string, list<Closure(array<string, mixed>): void>> */
    private array $handlers = [];

    #[Override]
    public function publish(string $channel, array $payload): void
    {
        foreach ($this->handlers[$channel] ?? [] as $handler) {
            $handler($payload);
        }
    }

    #[Override]
    public function subscribe(string $channel, Closure $handler): void
    {
        $this->handlers[$channel][] = $handler;
    }
}
