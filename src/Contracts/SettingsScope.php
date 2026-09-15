<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Enums\SettingScope;

/**
 * Addresses one cascade level of a setting/feature-flag override read or
 * write. A single instance names ONE level (account/workspace/user/
 * department) — composing the full cascade from one call happens inside the
 * consumer (`DatabaseSettingsRepository::cascade()`,
 * `DatabaseFeatureFlags::findOverride()`), not here.
 *
 * `department()` is reachable only through `FeatureFlags`
 * (`sys_feature_overrides.scope_type` CHECK allows it) — `SettingsRepository`
 * rejects it with `UnsupportedScopeException` (`sys_settings`'s CHECK does
 * not).
 *
 * @internal spec: B-10 §3.1
 */
final readonly class SettingsScope
{
    private function __construct(
        public SettingScope $type,
        public ?string $id = null,
    ) {}

    public static function account(): self
    {
        return new self(SettingScope::Account);
    }

    public static function workspace(string $workspaceId): self
    {
        return new self(SettingScope::Workspace, $workspaceId);
    }

    public static function user(string $userId): self
    {
        return new self(SettingScope::User, $userId);
    }

    public static function department(string $departmentId): self
    {
        return new self(SettingScope::Department, $departmentId);
    }
}
