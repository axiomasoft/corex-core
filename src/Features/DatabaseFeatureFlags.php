<?php

declare(strict_types=1);

namespace CoreX\Features;

use CoreX\Contracts\FeatureFlagDefinition;
use CoreX\Contracts\FeatureFlags;
use CoreX\Contracts\SettingsScope;
use CoreX\Enums\SettingScope;
use CoreX\Models\FeatureFlag;
use CoreX\Models\FeatureOverride;
use Override;

/**
 * Persisted feature flags on `sys_feature_flags`/`sys_feature_overrides`.
 * Replaces the interim config-driven `FeatureResolver`/
 * `ConfigFeatureResolver` — a single source of truth, no config-fallback
 * lane.
 *
 * Cascade order — DETERMINISTIC, declared here once:
 * `SettingScope::User`/`Department` (whichever the caller passes) beats
 * `Workspace` beats the flag's own account-level `is_enabled`/`payload`.
 * A single call resolves exactly the ONE override row matching the passed
 * `$scope` (exact `scope_type`+`scope_id`) — there is no auto-walk from a
 * narrower id to a broader one the caller didn't name (e.g. from a user id
 * to "that user's workspace"); a caller that needs several levels checked
 * calls `active()`/`value()` once per level it can resolve, narrowest first.
 *
 * `$connection` mirrors `DatabaseSettingsRepository`'s seam (null = app
 * default), used by the PG-only test lane.
 *
 * @internal spec: B-10 §3.1, AC-22, D27, D38, D12
 */
final class DatabaseFeatureFlags implements FeatureFlags
{
    public function __construct(
        private readonly ?string $connection = null,
    ) {}

    #[Override]
    public function active(string $key, ?SettingsScope $scope = null): bool
    {
        $flag = $this->find($key);

        if (! $flag instanceof FeatureFlag) {
            return false;
        }

        $override = $this->findOverride($flag->id, $scope);

        return $override instanceof FeatureOverride ? $override->is_enabled : $flag->is_enabled;
    }

    #[Override]
    public function value(string $key, ?SettingsScope $scope = null): mixed
    {
        $flag = $this->find($key);

        if (! $flag instanceof FeatureFlag) {
            return null;
        }

        $override = $this->findOverride($flag->id, $scope);

        // A NULL override payload means "this override only toggles
        // is_enabled" — it must fall back to the flag's own payload, not
        // blank it out from under every other scope (A87).
        if ($override instanceof FeatureOverride && $override->payload !== null) {
            return $override->payload;
        }

        return $flag->payload;
    }

    #[Override]
    public function define(FeatureFlagDefinition $def): void
    {
        // Atomic via the DB's UNIQUE(key) constraint (sys_feature_flags), not
        // a read-then-create — createOrFirst() isolates a losing INSERT in a
        // savepoint when called inside a surrounding transaction. Idempotent:
        // never stomps an admin-toggled `is_enabled`.
        FeatureFlag::on($this->connection)->createOrFirst(
            attributes: ['key' => $def->key],
            values: [
                'key' => $def->key,
                'module' => $def->module,
                'is_enabled' => $def->default,
                'payload' => $def->payload,
            ],
        );
    }

    private function find(string $key): ?FeatureFlag
    {
        /** @var FeatureFlag|null */
        return FeatureFlag::on($this->connection)->where('key', $key)->first();
    }

    /**
     * `null`/account scope has no override row — the flag's own `is_enabled`
     * IS the account-level value. Department scope is reachable here —
     * `sys_feature_overrides.scope_type` CHECK allows it and `SettingsScope`
     * now has a `department()` factory.
     *
     * @internal spec: D38
     */
    private function findOverride(string $flagId, ?SettingsScope $scope): ?FeatureOverride
    {
        if ($scope === null || $scope->type === SettingScope::Account) {
            return null;
        }

        /** @var FeatureOverride|null */
        return FeatureOverride::on($this->connection)
            ->where('flag_id', $flagId)
            ->where('scope_type', $scope->type->value)
            ->where('scope_id', $scope->id)
            ->first();
    }
}
