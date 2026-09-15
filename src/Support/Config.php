<?php

declare(strict_types=1);

namespace CoreX\Support;

use CoreX\Audit\QueuedAuditLogger;
use CoreX\Contracts\AuditLogger;
use CoreX\Contracts\DepartmentRepository;
use CoreX\Contracts\FeatureFlags;
use CoreX\Contracts\SettingDefaultsProvider;
use CoreX\Contracts\SettingsRepository;
use CoreX\Departments\DatabaseDepartmentRepository;
use CoreX\Enums\IdStrategy;
use CoreX\Enums\PubSubDriver;
use CoreX\Features\DatabaseFeatureFlags;
use CoreX\Settings\DatabaseSettingsRepository;
use CoreX\Settings\NullSettingDefaultsProvider;
use CoreX\Tenancy\Contracts\ImpersonationService;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\NullImpersonationService;
use CoreX\Tenancy\NullTenancyManager;
use CoreX\Tenancy\SingleAccountResolver;

/**
 * Typed accessor over config('corex.*') — keeps string keys in one place.
 */
final class Config
{
    public static function idStrategy(): IdStrategy
    {
        $value = config('corex.ids.strategy', IdStrategy::Uuid7);

        return $value instanceof IdStrategy ? $value : IdStrategy::from((string) $value);
    }

    public static function settingsTable(): string
    {
        return (string) config('corex.tables.settings', 'sys_settings');
    }

    public static function auditLogsTable(): string
    {
        return (string) config('corex.tables.audit_logs', 'sys_audit_log');
    }

    public static function featureFlagsTable(): string
    {
        return (string) config('corex.tables.feature_flags', 'sys_feature_flags');
    }

    public static function featureOverridesTable(): string
    {
        return (string) config('corex.tables.feature_overrides', 'sys_feature_overrides');
    }

    public static function departmentsTable(): string
    {
        return (string) config('corex.tables.departments', 'sys_departments');
    }

    public static function departmentUserTable(): string
    {
        return (string) config('corex.tables.department_user', 'sys_department_user');
    }

    // ─── Tenant context ───────────────────────────────────────────────────

    /** @return class-string<TenantContextResolver> */
    public static function tenantContextResolverClass(): string
    {
        /** @var class-string<TenantContextResolver> $class */
        $class = config('corex.tenant_context.resolver', SingleAccountResolver::class);

        return $class;
    }

    /** @return class-string<TenancyManager> */
    public static function tenancyManagerClass(): string
    {
        /** @var class-string<TenancyManager> $class */
        $class = config('corex.tenant_context.manager', NullTenancyManager::class);

        return $class;
    }

    /** @return class-string<ImpersonationService> */
    public static function impersonationServiceClass(): string
    {
        /** @var class-string<ImpersonationService> $class */
        $class = config('corex.tenant_context.impersonation', NullImpersonationService::class);

        return $class;
    }

    // ─── Bindings ─────────────────────────────────────────────────────────

    /** @return class-string<SettingsRepository> */
    public static function settingsRepositoryClass(): string
    {
        /** @var class-string<SettingsRepository> $class */
        $class = config('corex.bindings.settings', DatabaseSettingsRepository::class);

        return $class;
    }

    /** @return class-string<AuditLogger> */
    public static function auditLoggerClass(): string
    {
        /** @var class-string<AuditLogger> $class */
        $class = config('corex.bindings.audit_logger', QueuedAuditLogger::class);

        return $class;
    }

    /** @return class-string<SettingDefaultsProvider> */
    public static function settingDefaultsProviderClass(): string
    {
        /** @var class-string<SettingDefaultsProvider> $class */
        $class = config('corex.bindings.setting_defaults', NullSettingDefaultsProvider::class);

        return $class;
    }

    /** @return class-string<FeatureFlags> */
    public static function featureFlagsClass(): string
    {
        /** @var class-string<FeatureFlags> $class */
        $class = config('corex.bindings.feature_flags', DatabaseFeatureFlags::class);

        return $class;
    }

    /** @return class-string<DepartmentRepository> */
    public static function departmentRepositoryClass(): string
    {
        /** @var class-string<DepartmentRepository> $class */
        $class = config('corex.bindings.departments', DatabaseDepartmentRepository::class);

        return $class;
    }

    // ─── Audit ────────────────────────────────────────────────────────────

    public static function auditEnabled(): bool
    {
        return (bool) config('corex.audit.enabled', true);
    }

    // ─── Settings cache ───────────────────────────────────────────────────

    public static function settingsCacheStore(): string
    {
        return (string) config('corex.settings.cache_store', 'array');
    }

    public static function settingsCacheTtl(): ?int
    {
        $value = config('corex.settings.cache_ttl', 3600);

        return $value !== null ? (int) $value : null;
    }

    public static function settingsCachePrefix(): string
    {
        return (string) config('corex.settings.cache_prefix', 'corex.settings');
    }

    // ─── PubSub ───────────────────────────────────────────────────────────

    public static function pubsubMessagesTable(): string
    {
        return (string) config('corex.tables.pubsub_messages', 'sys_pubsub_messages');
    }

    public static function pubsubCursorsTable(): string
    {
        return (string) config('corex.tables.pubsub_cursors', 'sys_pubsub_cursors');
    }

    public static function pubsubDriver(): PubSubDriver
    {
        $value = config('corex.pubsub.driver', PubSubDriver::Null);

        return $value instanceof PubSubDriver ? $value : PubSubDriver::from((string) $value);
    }

    public static function pubsubPollIntervalMicroseconds(): int
    {
        return (int) config('corex.pubsub.poll_interval_microseconds', 50_000);
    }

    /**
     * Opt-in flag a dedicated worker command sets before calling
     * `subscribe()` under an Octane runtime — `runningInConsole()`
     * cannot be trusted there (see `CoreX\Contracts\WorkerRuntime`), so the
     * guard requires this explicit signal instead. Never set by
     * request-handling code.
     *
     * @internal spec: D44
     */
    public static function pubsubOctaneWorkerOptIn(): bool
    {
        return (bool) config('corex.pubsub.octane_worker_opt_in', false);
    }

    /**
     * Default `--older-than` for `corex:pubsub:prune` — a
     * `DateInterval`-parseable relative-time string. MUST stay longer than
     * the worst-case subscriber downtime: pruning a message before every
     * durable cursor behind it has passed it is a silent, permanent
     * invalidation loss.
     *
     * @internal spec: D44
     */
    public static function pubsubRetention(): string
    {
        return (string) config('corex.pubsub.retention', '30 days');
    }

    public static function storageDurableRoot(): string
    {
        return (string) config('corex.storage.durable_root', storage_path('app/corex/durable'));
    }

    public static function storageEphemeralRoot(): string
    {
        return (string) config('corex.storage.ephemeral_root', storage_path('app/corex/ephemeral'));
    }
}
