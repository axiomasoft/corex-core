<?php

declare(strict_types=1);

use CoreX\Support\Config;
use CoreX\Support\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PG-only (D12), depends on sys_feature_flags (previous migration in this
// batch). Published under `corex-migrations`/`corex-migrations-tenant`
// (D33/D43). id/flag_id/scope_id follow config('corex.ids.strategy') via
// IdColumns (D3/A58); timestampTz precision is explicit µs (D32/A66). See
// DB_SCHEMA.md §3.2 sys_feature_overrides; B-10 §2 DDL.
return new class extends Migration
{
    public function up(): void
    {
        $table = Config::featureOverridesTable();

        Schema::create($table, function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::foreign($table, 'flag_id')->constrained(Config::featureFlagsTable())->cascadeOnDelete();
            $table->string('scope_type', 16);
            IdColumns::reference($table, 'scope_id');
            $table->boolean('is_enabled');
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_fo_scope_ck CHECK (scope_type IN ('workspace','user','department'))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_fo_uq UNIQUE (flag_id, scope_type, scope_id)");
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::featureOverridesTable());
    }
};
