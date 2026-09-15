<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Manifest-declared feature-flag default. `FeatureFlags::define()` is
 * idempotent — registering the same key again never overwrites an
 * admin-toggled `is_enabled` (mirrors `SettingDefaultsProvider`'s
 * register-once semantics).
 *
 * @internal spec: B-10 §3.1
 */
final readonly class FeatureFlagDefinition
{
    /** @param  array<string, mixed>|null  $payload */
    public function __construct(
        public string $key,
        public bool $default = false,
        public ?string $module = null,
        public ?array $payload = null,
    ) {}

    public static function make(string $key, bool $default = false): self
    {
        return new self($key, $default);
    }

    public function module(string $module): self
    {
        return new self(
            key: $this->key,
            default: $this->default,
            module: $module,
            payload: $this->payload,
        );
    }

    /** @param  array<string, mixed>  $payload */
    public function payload(array $payload): self
    {
        return new self(
            key: $this->key,
            default: $this->default,
            module: $this->module,
            payload: $payload,
        );
    }
}
