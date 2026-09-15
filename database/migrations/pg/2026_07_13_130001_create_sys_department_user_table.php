<?php

declare(strict_types=1);

use CoreX\Support\Config;
use CoreX\Support\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PG-only (D12), depends on sys_departments (previous migration in this
// batch). Published under `corex-migrations`/`corex-migrations-tenant`
// (D33/D43). department_id/user_id follow config('corex.ids.strategy') via
// IdColumns (D3/A58); user_id is a loose reference — the FK lands with
// corex/auth (P2, DB_SCHEMA C4). timestampTz precision is explicit µs
// (D32/A66). See DB_SCHEMA.md §3.2 sys_department_user; B-10 §2 DDL.
return new class extends Migration
{
    public function up(): void
    {
        $table = Config::departmentUserTable();

        Schema::create($table, function (Blueprint $table): void {
            IdColumns::foreign($table, 'department_id')->constrained(Config::departmentsTable())->cascadeOnDelete();
            IdColumns::reference($table, 'user_id');
            $table->string('role', 16)->default('member');
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->primary(['department_id', 'user_id']);
        });

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_du_role_ck CHECK (role IN ('member','head','deputy'))");
        DB::statement("CREATE UNIQUE INDEX sys_du_primary_uq ON {$table} (user_id) WHERE is_primary");
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::departmentUserTable());
    }
};
