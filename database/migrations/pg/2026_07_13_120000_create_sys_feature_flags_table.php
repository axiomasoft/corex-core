<?php

declare(strict_types=1);

use CoreX\Support\Config;
use CoreX\Support\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PG-only (D12) — mirrors sys_settings/sys_audit_log: published under the tag
// `corex-migrations`/`corex-migrations-tenant` (D33/D43), not auto-loaded on
// the sqlite default lane. id follows config('corex.ids.strategy') via
// IdColumns (D3/A58); timestampTz precision is explicit µs (D32/A66). See
// DB_SCHEMA.md §3.2 sys_feature_flags for the column-by-column source; B-10 §2
// for the DDL this mirrors.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Config::featureFlagsTable(), function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('key', 128)->unique();
            $table->string('module', 190)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::featureFlagsTable());
    }
};
