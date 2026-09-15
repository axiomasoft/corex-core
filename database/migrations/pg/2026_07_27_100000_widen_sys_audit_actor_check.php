<?php

declare(strict_types=1);

use CoreX\Support\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// P2.11 (D137/OQ-7 часть 1) — additive widening of the CHECK the closed
// P1.8 migration created: `support` (B-11 §5.9 impersonation ACTOR) joins
// the vocabulary. down() restores the ORIGINAL four-value list — the closed
// migration file itself is never edited (D137).
return new class extends Migration
{
    public function up(): void
    {
        $table = Config::auditLogsTable();

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT sys_audit_actor_ck");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_audit_actor_ck CHECK (actor_type IN ('user','agent','api_key','system','support'))");
    }

    public function down(): void
    {
        $table = Config::auditLogsTable();

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT sys_audit_actor_ck");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_audit_actor_ck CHECK (actor_type IN ('user','agent','api_key','system'))");
    }
};
