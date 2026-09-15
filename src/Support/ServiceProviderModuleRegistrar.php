<?php

declare(strict_types=1);

namespace CoreX\Support;

use CoreX\Contracts\ModuleRegistrar;
use Illuminate\Contracts\Foundation\Application;

/**
 * Lightweight, own module loader: a module's top-level provider lists its
 * child providers, which are registered through the application container.
 * Top-level providers are still discovered by Laravel package auto-discovery.
 */
final class ServiceProviderModuleRegistrar implements ModuleRegistrar
{
    /** @var list<class-string> */
    private array $registered = [];

    public function __construct(
        private readonly Application $app,
    ) {}

    public function register(string ...$providers): void
    {
        foreach ($providers as $provider) {
            $this->app->register($provider);
            $this->registered[] = $provider;
        }
    }

    public function registered(): array
    {
        return $this->registered;
    }
}
