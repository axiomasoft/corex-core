<?php

declare(strict_types=1);

namespace CoreX;

use CoreX\Audit\NullAuditRetention;
use CoreX\Audit\PruneAuditLog;
use CoreX\Contracts\AuditLogger;
use CoreX\Contracts\AuditRetention;
use CoreX\Contracts\DepartmentRepository;
use CoreX\Contracts\FeatureFlags;
use CoreX\Contracts\ModuleRegistrar;
use CoreX\Contracts\PubSub;
use CoreX\Contracts\SettingDefaultsProvider;
use CoreX\Contracts\SettingsRepository;
use CoreX\Contracts\TenantStorage;
use CoreX\Contracts\WorkerRuntime;
use CoreX\PubSub\DefaultWorkerRuntime;
use CoreX\PubSub\PrunePubSubMessages;
use CoreX\PubSub\PubSubManager;
use CoreX\Storage\LocalTenantStorage;
use CoreX\Support\Config;
use CoreX\Support\ServiceProviderModuleRegistrar;
use CoreX\Tenancy\Contracts\ImpersonationService;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\ServiceProvider;
use Override;

final class CoreServiceProvider extends ServiceProvider
{
    /**
     * Shipped schema generation of the sys_* migration set. A consumer
     * publishes a COPY of the migrations, so a package upgrade cannot mutate
     * them in place — schema changes ship as NEW additive migration files under
     * the same publish tag and bump this constant. Lets a host verify which
     * generation its published set is on before re-running vendor:publish.
     *
     * @internal spec: D43
     */
    public const int SCHEMA_VERSION = 1;

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex.php', 'corex');

        $this->app->singleton(SettingDefaultsProvider::class, Config::settingDefaultsProviderClass());
        $this->app->singleton(SettingsRepository::class, Config::settingsRepositoryClass());
        $this->app->singleton(AuditLogger::class, Config::auditLoggerClass());

        /** @var class-string<AuditRetention> $auditRetention */
        $auditRetention = config('corex.bindings.audit_retention', NullAuditRetention::class);
        $this->app->singleton(AuditRetention::class, $auditRetention);

        $this->app->singleton(TenantContextResolver::class, Config::tenantContextResolverClass());
        $this->app->singleton(TenancyManager::class, Config::tenancyManagerClass());
        $this->app->singleton(ImpersonationService::class, Config::impersonationServiceClass());
        $this->app->singleton(ModuleRegistrar::class, ServiceProviderModuleRegistrar::class);
        $this->app->singleton(FeatureFlags::class, Config::featureFlagsClass());
        $this->app->singleton(DepartmentRepository::class, Config::departmentRepositoryClass());
        $this->app->singleton(PubSub::class, PubSubManager::class);
        $this->app->singleton(WorkerRuntime::class, DefaultWorkerRuntime::class);
        $this->app->scoped(TenantStorage::class, function ($app): LocalTenantStorage {
            return new LocalTenantStorage(
                contexts: $app->make(TenantContextResolver::class),
                filesystems: new FilesystemManager($app),
                durableRoot: Config::storageDurableRoot(),
                ephemeralRoot: Config::storageEphemeralRoot(),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PruneAuditLog::class, PrunePubSubMessages::class]);
        }

        // NO loadMigrationsFrom: every sys_* migration is PG-only
        // DDL living in database/migrations/pg/, and this provider is registered
        // by sqlite-backed consumer suites too — an unconditional load would
        // break them. The old call
        // pointed at the now-empty database/migrations/ root (the scan is
        // non-recursive), so it silently loaded ZERO tables while reading as
        // "migrations are wired" — removed. Consumers get the schema through
        // publish + an explicit, tenant-scoped migrate (see README).
        //
        // Connection-scoped publish: sys_* is TENANT-schema DDL. The
        // `-tenant` tag names that scope so a consumer never runs it on the
        // central/root connection by accident (pre-mortem P1R C5) — the plain
        // `corex-migrations` alias stays for the common single-DB (boxed) case.
        // Under P2 (DB-per-account) the consumer runs
        // `migrate --database=<tenant>` after publishing.
        $this->publishes([
            __DIR__.'/../database/migrations/pg' => database_path('migrations'),
        ], ['corex-migrations', 'corex-migrations-tenant']);

        $this->publishes([
            __DIR__.'/../config/corex.php' => config_path('corex.php'),
        ], 'corex-config');
    }
}
