<?php

declare(strict_types=1);

namespace CoreX\PubSub;

use Closure;
use CoreX\Contracts\PubSub;
use CoreX\Enums\PubSubDriver;
use CoreX\Support\Config;
use Illuminate\Contracts\Foundation\Application;
use Override;

/**
 * Resolves the configured PubSub driver (redis cloud-default, database/null
 * boxed fallbacks) — no `if (cloud)` branching in business code, only this
 * config-driven switch. Bound as a singleton (CoreServiceProvider) so
 * `NullPubSub`'s in-memory subscriber list survives across a process's
 * publish()/subscribe() calls.
 *
 * @internal spec: B-10 §6.1, D10
 */
final class PubSubManager implements PubSub
{
    private ?PubSub $driver = null;

    public function __construct(
        private readonly Application $app,
    ) {}

    #[Override]
    public function publish(string $channel, array $payload): void
    {
        $this->driver()->publish($channel, $payload);
    }

    #[Override]
    public function subscribe(string $channel, Closure $handler): void
    {
        $this->driver()->subscribe($channel, $handler);
    }

    private function driver(): PubSub
    {
        return $this->driver ??= match (Config::pubsubDriver()) {
            PubSubDriver::Redis => $this->app->make(RedisPubSub::class),
            PubSubDriver::Database => $this->app->make(DatabasePollPubSub::class),
            PubSubDriver::Null => $this->app->make(NullPubSub::class),
        };
    }
}
