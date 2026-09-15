<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Per-account feature-flag store with workspace/user/department overrides.
 * `$scope` addresses ONE override level — `null` reads the account-level
 * default carried by the flag row itself. Cascade order is DETERMINISTIC
 * (declared in `DatabaseFeatureFlags`'s docblock): whichever narrow level
 * the caller passed (user/department) beats workspace beats the flag's own
 * account-level default — one call resolves exactly the level named by
 * `$scope`, no implicit walk to a broader id the caller didn't supply.
 *
 * @internal spec: B-10 §3.1
 */
interface FeatureFlags
{
    public function active(string $key, ?SettingsScope $scope = null): bool;

    public function value(string $key, ?SettingsScope $scope = null): mixed;

    /** Idempotent — a second `define()` for the same key never overwrites an admin-toggled `is_enabled`. */
    public function define(FeatureFlagDefinition $def): void;
}
