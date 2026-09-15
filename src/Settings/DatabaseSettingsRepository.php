<?php

declare(strict_types=1);

namespace CoreX\Settings;

use CoreX\Contracts\SettingDefaultsProvider;
use CoreX\Contracts\SettingsRepository;
use CoreX\Contracts\SettingsScope;
use CoreX\Enums\SettingScope;
use CoreX\Events\SettingChanged;
use CoreX\Exceptions\SettingValueTooLargeException;
use CoreX\Exceptions\UnsupportedScopeException;
use CoreX\Models\Setting;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * Cascade: user → workspace → account → manifest default
 * ({@see SettingDefaultsProvider}). One `SettingsScope` names a single
 * level; a user-scoped read falls through to account/default but NOT
 * through workspace — auto-resolving "this user's workspace" needs tenancy
 * context and is out of scope here. Callers that need the workspace level
 * explicitly pass `SettingsScope::workspace()`.
 * `SettingsScope::department()` is a `FeatureFlags`-only level — every
 * method here throws `UnsupportedScopeException` for it (`sys_settings`
 * has no `department` in its CHECK).
 *
 * `$connection` is null (the app's default) unless a caller pins an explicit
 * name — same seam as DatabaseRecordsRegistrar, used by the PG-only test
 * lane since `sys_settings` has no sqlite equivalent.
 *
 * @internal spec: B-10 §3.1, P1.7 (Scope Excluded), D38, P1.6, D12
 */
final class DatabaseSettingsRepository implements SettingsRepository
{
    private const MAX_VALUE_BYTES = 65536;

    public function __construct(
        private readonly SettingDefaultsProvider $defaults,
        private readonly ?string $connection = null,
    ) {}

    #[Override]
    public function get(string $namespace, string $key, ?SettingsScope $scope = null, mixed $default = null): mixed
    {
        foreach ($this->cascade($scope) as [$scopeType, $scopeId]) {
            $row = $this->find($namespace, $key, $scopeType, $scopeId);

            if ($row instanceof Setting) {
                return $this->decode($row);
            }
        }

        return $this->defaults->defaultFor($namespace, $key) ?? $default;
    }

    #[Override]
    public function set(string $namespace, string $key, mixed $value, ?SettingsScope $scope = null): void
    {
        [$scopeType, $scopeId] = $this->level($scope);
        $sensitive = $this->defaults->isSensitive($namespace, $key);

        $encoded = json_encode($value, JSON_THROW_ON_ERROR);

        if (strlen($encoded) > self::MAX_VALUE_BYTES) {
            throw new SettingValueTooLargeException($namespace, $key, strlen($encoded), self::MAX_VALUE_BYTES);
        }

        $old = $this->find($namespace, $key, $scopeType, $scopeId);
        $oldValue = $old instanceof Setting ? $this->decode($old) : null;
        $changed = ! $old instanceof Setting || $oldValue !== $value;

        $stored = $sensitive ? json_encode(Crypt::encryptString($encoded), JSON_THROW_ON_ERROR) : $encoded;

        Setting::on($this->connection)->updateOrCreate(
            attributes: ['namespace' => $namespace, 'key' => $key, 'scope_type' => $scopeType->value, 'scope_id' => $scopeId],
            values: ['value' => $stored, 'is_sensitive' => $sensitive],
        );

        // Only on an actual value change, and after the write commits — a
        // no-op set() (same value re-written) must not spam listeners, and a
        // rolled-back transaction must not have fired the event at all.
        if ($changed) {
            $this->db()->afterCommit(fn () => event(new SettingChanged(
                namespace: $namespace,
                key: $key,
                scopeType: $scopeType,
                scopeId: $scopeId,
                old: $oldValue,
                new: $value,
                sensitive: $sensitive,
            )));
        }
    }

    #[Override]
    public function forget(string $namespace, string $key, ?SettingsScope $scope = null): void
    {
        [$scopeType, $scopeId] = $this->level($scope);

        Setting::on($this->connection)
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->where('scope_type', $scopeType->value)
            ->where('scope_id', $scopeId)
            ->delete();
    }

    #[Override]
    public function all(string $namespace, ?SettingsScope $scope = null): array
    {
        $merged = [];

        // Broadest level first so a narrower level's rows overwrite it below.
        foreach (array_reverse($this->cascade($scope)) as [$scopeType, $scopeId]) {
            $rows = Setting::on($this->connection)
                ->where('namespace', $namespace)
                ->where('scope_type', $scopeType->value)
                ->where('scope_id', $scopeId)
                ->get();

            foreach ($rows as $row) {
                $merged[$row->key] = $this->decode($row);
            }
        }

        return $merged;
    }

    private function db(): Connection
    {
        return DB::connection($this->connection);
    }

    private function find(string $namespace, string $key, SettingScope $scopeType, ?string $scopeId): ?Setting
    {
        /** @var Setting|null */
        return Setting::on($this->connection)
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->where('scope_type', $scopeType->value)
            ->where('scope_id', $scopeId)
            ->first();
    }

    private function decode(Setting $row): mixed
    {
        $raw = $row->value;

        if ($row->is_sensitive) {
            $cipher = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $raw = Crypt::decryptString($cipher);
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array{0: SettingScope, 1: string|null} */
    private function level(?SettingsScope $scope): array
    {
        if ($scope?->type === SettingScope::Department) {
            throw new UnsupportedScopeException($scope->type);
        }

        return $scope === null ? [SettingScope::Account, null] : [$scope->type, $scope->id];
    }

    /** @return list<array{0: SettingScope, 1: string|null}> */
    private function cascade(?SettingsScope $scope): array
    {
        return match ($scope?->type) {
            null, SettingScope::Account => [[SettingScope::Account, null]],
            SettingScope::Workspace => [[SettingScope::Workspace, $scope->id], [SettingScope::Account, null]],
            SettingScope::User => [[SettingScope::User, $scope->id], [SettingScope::Account, null]],
            SettingScope::Department => throw new UnsupportedScopeException(SettingScope::Department),
        };
    }
}
