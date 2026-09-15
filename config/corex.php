<?php

declare(strict_types=1);

use CoreX\Audit\NullAuditRetention;
use CoreX\Audit\QueuedAuditLogger;
use CoreX\Departments\DatabaseDepartmentRepository;
use CoreX\Enums\IdStrategy;
use CoreX\Enums\PubSubDriver;
use CoreX\Features\DatabaseFeatureFlags;
use CoreX\Settings\DatabaseSettingsRepository;
use CoreX\Settings\NullSettingDefaultsProvider;
use CoreX\Tenancy\NullImpersonationService;
use CoreX\Tenancy\NullTenancyManager;
use CoreX\Tenancy\SingleAccountResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Identifier strategy
    |--------------------------------------------------------------------------
    | Primary-key strategy for BaseModel. UUIDv7 is the default (ADR-003
    | revision); Ulid/Uuid/Int seams remain for boxed/legacy installs.
    */
    'ids' => [
        'strategy' => IdStrategy::Uuid7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    | Override if the defaults collide with existing tables in a host app.
    */
    'tables' => [
        'settings' => 'sys_settings',
        'audit_logs' => 'sys_audit_log',
        'feature_flags' => 'sys_feature_flags',
        'feature_overrides' => 'sys_feature_overrides',
        'departments' => 'sys_departments',
        'department_user' => 'sys_department_user',
        'pubsub_messages' => 'sys_pubsub_messages',
        'pubsub_cursors' => 'sys_pubsub_cursors',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant context (B-10 §3.1 / B-11 §3.1)
    |--------------------------------------------------------------------------
    | TenantContextResolver binding. Boxed installs default to
    | SingleAccountResolver (constant context, no real tenancy). Real
    | resolvers (host/root, corex/tenancy) land in P2.
    |
    | `manager` (P2.5): TenancyManager binding — single switch point for
    | connection/cache/queue/fs. Boxed default is a null-manager (no-op,
    | constant context via `resolver` above); corex/tenancy rebinds this to
    | StanclTenancyManager when the package is present (D10 — container
    | binding, never an `if(cloud)` branch).
    |
    | `impersonation` (P2.11, D139): ImpersonationService binding — boxed
    | default has no grants at all (B-11 §6.1); corex/tenancy rebinds this to
    | DatabaseImpersonationService under `tenancy.oidc.impersonation.enabled`.
    */
    'tenant_context' => [
        'resolver' => SingleAccountResolver::class,
        'manager' => NullTenancyManager::class,
        'impersonation' => NullImpersonationService::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bindings
    |--------------------------------------------------------------------------
    | Swap any core contract implementation here.
    */
    'bindings' => [
        'settings' => DatabaseSettingsRepository::class,
        'audit_logger' => QueuedAuditLogger::class,
        'audit_retention' => NullAuditRetention::class,
        'setting_defaults' => NullSettingDefaultsProvider::class,
        'feature_flags' => DatabaseFeatureFlags::class,
        'departments' => DatabaseDepartmentRepository::class,
    ],

    'storage' => [
        'durable_root' => storage_path('app/corex/durable'),
        'ephemeral_root' => storage_path('app/corex/ephemeral'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings cache
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'cache_store' => 'array',
        'cache_ttl' => 3600,
        'cache_prefix' => 'corex.settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | PubSub (B-10 §3.1/§6.1/§7.3 — no LISTEN/NOTIFY, R-15)
    |--------------------------------------------------------------------------
    | 'null' is the boxed-safe default (no infra dependency). 'redis' is the
    | cloud default (B-10 §6.1); 'database' is the boxed fallback when Redis
    | is unavailable.
    |
    | `octane_worker_opt_in` (D44/A29): a dedicated worker command sets this
    | to true before calling subscribe() under Octane — runningInConsole()
    | alone cannot tell an HTTP request from a worker command apart there.
    | Never set it from request-handling code.
    |
    | `retention`: corex:pubsub:prune's default --older-than window for
    | sys_pubsub_messages. Schedule it (e.g. daily, php artisan schedule)
    | longer than the worst-case subscriber downtime — pruning a message
    | before every durable cursor behind it has passed it is a silent,
    | permanent invalidation loss (D44).
    */
    'pubsub' => [
        'driver' => PubSubDriver::Null,
        'poll_interval_microseconds' => 50_000,
        'octane_worker_opt_in' => false,
        'retention' => '30 days',
    ],

];
