<?php

declare(strict_types=1);

use CoreX\Support\Config;
use CoreX\Support\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PG-only (D12): jsonb + CHECK + UNIQUE NULLS NOT DISTINCT have no sqlite
// equivalent. NOT auto-loaded — CoreServiceProvider publishes this dir under
// the tag `corex-migrations`/`corex-migrations-tenant` (D33/D43); the consumer
// runs `vendor:publish` then `migrate --database=<tenant>`. PG-lane tests
// migrate it explicitly by --path (tests/Feature/SettingsTest.php), same
// pattern as ModuleLifecycleTest. Replaces corex_settings (D1/D36 — greenfield,
// no prod data to migrate).
// id/scope_id follow config('corex.ids.strategy') via IdColumns (D3/A58): the
// key seam is public, so a hardcoded uuid would break ulid/int installs.
// timestampTz precision is explicit µs (D32/A66): PG rounds timestamp(0), which
// would corrupt chronological order in time-sensitive tables.
// See DB_SCHEMA.md §3.2 sys_settings for the column-by-column source.
return new class extends Migration
{
    public function up(): void
    {
        $table = Config::settingsTable();

        Schema::create($table, function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('namespace', 64);
            $table->string('key', 190);
            $table->string('scope_type', 16)->default('account');
            IdColumns::reference($table, 'scope_id')->nullable();
            $table->jsonb('value');
            $table->boolean('is_sensitive')->default(false);
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_settings_scope_ck CHECK (scope_type IN ('account','workspace','user'))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_settings_scope_id_ck CHECK ((scope_type = 'account' AND scope_id IS NULL) OR (scope_type <> 'account' AND scope_id IS NOT NULL))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_settings_uq UNIQUE NULLS NOT DISTINCT (namespace, key, scope_type, scope_id)");
        // Beyond the DRAFT DB_SCHEMA catalog (A91, documented deviation): a
        // scope lookup index for the cascade resolver. Kept, not dropped — the
        // catalog is silent on it, not in conflict; recorded in P1.19 report.
        DB::statement("CREATE INDEX sys_settings_scope_ix ON {$table} (scope_type, scope_id)");
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::settingsTable());
    }
};
