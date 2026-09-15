<?php

declare(strict_types=1);

use CoreX\Support\Config;
use CoreX\Support\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PG-only (D12): ltree/GIST have no sqlite equivalent. Published under the tag
// `corex-migrations`/`corex-migrations-tenant` (D33/D43), not auto-loaded on
// the sqlite default lane. id/workspace_id/parent_id/head_user_id follow
// config('corex.ids.strategy') via IdColumns (D3/A58); workspace_id/head_user_id
// are loose references — the FKs land with corex/tenancy and corex/auth (P2,
// DB_SCHEMA C4). timestampTz precision is explicit µs (D32/A66). See
// DB_SCHEMA.md §3.2 sys_departments; B-10 §2 for the DDL this mirrors.
return new class extends Migration
{
    public function up(): void
    {
        // Trusted extension since PG13 — role with CREATE on the database
        // suffices, no superuser required (RAG:✅ 2026-07-12 findings §4).
        DB::statement('CREATE EXTENSION IF NOT EXISTS ltree');

        $table = Config::departmentsTable();

        Schema::create($table, function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::reference($table, 'workspace_id');
            IdColumns::reference($table, 'parent_id')->nullable();
            $table->string('name', 255);
            IdColumns::reference($table, 'head_user_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestampTz('deleted_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        // Self-referencing FK added AFTER the blueprint's own `primary`
        // command has run (Schema::create() defers the fluent ->primary()
        // to after any inline ->constrained(), which would try to add this
        // FK before sys_departments.id has a PK to reference).
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_dept_parent_fk FOREIGN KEY (parent_id) REFERENCES {$table} (id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE {$table} ADD COLUMN path ltree NOT NULL");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_dept_path_uq UNIQUE (path)");
        DB::statement("CREATE INDEX sys_dept_path_gist ON {$table} USING GIST (path)");
        DB::statement("CREATE INDEX sys_dept_wsp_ix ON {$table} (workspace_id) WHERE deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::departmentsTable());
    }
};
