<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Hierarchical settings store: user → workspace → account →
 * manifest-declared default ({@see SettingDefaultsProvider}).
 *
 * @internal spec: B-10 §3.1
 */
interface SettingsRepository
{
    public function get(string $namespace, string $key, ?SettingsScope $scope = null, mixed $default = null): mixed;

    public function set(string $namespace, string $key, mixed $value, ?SettingsScope $scope = null): void;

    public function forget(string $namespace, string $key, ?SettingsScope $scope = null): void;

    /** @return array<string, mixed> merged by cascade — narrower scope overrides broader */
    public function all(string $namespace, ?SettingsScope $scope = null): array;
}
