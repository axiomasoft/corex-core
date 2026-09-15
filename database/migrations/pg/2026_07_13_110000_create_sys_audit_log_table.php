<?php

declare(strict_types=1);

use CoreX\Support\Config;
use CoreX\Support\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PG-only (D12): jsonb + CHECK + BRIN have no sqlite equivalent. NOT
// auto-loaded — CoreServiceProvider publishes migrations/pg/ under the tag
// `corex-migrations`/`corex-migrations-tenant` (D33/D43). PG-lane tests migrate
// it explicitly by --path (AuditLogTest), same pattern as
// SettingsTest/ModuleLifecycleTest. Replaces corex_audit_logs (D1/D36 —
// greenfield). No updated_at: DB_SCHEMA.md §Конфликт-2 (resolved 2026-07-13) —
// append-only with zero legitimate post-insert fields does not get one.
// id/actor_id/workspace_id/entity_id follow config('corex.ids.strategy') via
// IdColumns (D3/A58). timestampTz precision is explicit µs (D32/A66/A72): PG
// ROUNDS timestamp(0), so occurred_at at .600 would be written as +1s — the
// chronological order of a legally significant append-only audit must not lie.
// `seq bigserial` (D42/pre-mortem P1R C6) is the monotonic audit cursor:
// UUIDv7 `id` (ms precision + random tail) and occurred_at (accepting-node
// clock) do not give monotonicity across nodes, so keyset pagination sorts and
// tie-breaks ONLY by seq. bigserial is a plain surrogate cursor, NOT the PK
// (id stays the UUIDv7 identity) — beyond the DRAFT DB_SCHEMA catalog, D-log
// D42.
// See DB_SCHEMA.md §3.2 sys_audit_log for the column-by-column source.
return new class extends Migration
{
    public function up(): void
    {
        $table = Config::auditLogsTable();

        Schema::create($table, function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->timestampTz('occurred_at', precision: 6)->useCurrent();
            $table->string('actor_type', 16);
            IdColumns::reference($table, 'actor_id')->nullable();
            IdColumns::reference($table, 'workspace_id')->nullable();
            $table->string('entity_type', 64)->nullable();
            IdColumns::reference($table, 'entity_id')->nullable();
            $table->string('action', 64);
            $table->jsonb('changes')->nullable();
            $table->jsonb('context')->default('{}');
            $table->timestampTz('created_at', precision: 6)->useCurrent();
        });

        // Monotonic audit cursor (D42): clock-free bigserial assigned in insert
        // order. Keyset pagination `WHERE seq > :cursor ORDER BY seq` is the ONLY
        // stable ordering of this journal — hence the btree index on seq.
        DB::statement("ALTER TABLE {$table} ADD COLUMN seq bigserial");

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_audit_actor_ck CHECK (actor_type IN ('user','agent','api_key','system'))");
        DB::statement("CREATE INDEX sys_audit_seq_ix ON {$table} (seq)");
        DB::statement("CREATE INDEX sys_audit_entity_ix ON {$table} (entity_type, entity_id, occurred_at DESC)");
        DB::statement("CREATE INDEX sys_audit_actor_ix ON {$table} (actor_type, actor_id, occurred_at DESC)");
        DB::statement("CREATE INDEX sys_audit_wsp_ix ON {$table} (workspace_id, occurred_at DESC)");
        DB::statement("CREATE INDEX sys_audit_brin_ix ON {$table} USING BRIN (occurred_at)");
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::auditLogsTable());
    }
};
