<?php

declare(strict_types=1);

use CoreX\Support\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PG-only (D12), same publish/connection scoping as sys_pubsub_messages
// (D33/D43). Backs DatabasePollPubSub's DURABLE cursor (D44/P1.24): the poll
// position for a (channel, subscriber) pair survives a subscriber restart
// instead of resetting to 0 (replays all history) or max(id) on every
// subscribe() (silently drops the restart-window backlog — plan.md D44,
// pre-mortem P1R C7). Driver-local delivery-cursor state, not a domain
// entity — same NOT-in-DB_SCHEMA.md / NOT-IdColumns rationale as
// sys_pubsub_messages.id (OQ-6, audit F12; P1.19 Scope Excluded).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Config::pubsubCursorsTable(), function (Blueprint $table): void {
            $table->string('channel', 190);
            $table->string('subscriber', 190);
            $table->unsignedBigInteger('position');
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->primary(['channel', 'subscriber']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::pubsubCursorsTable());
    }
};
