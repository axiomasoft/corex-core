<?php

declare(strict_types=1);

use CoreX\Support\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Config::savedViewScopesTable(), function (Blueprint $table): void {
            $table->string('entity_code', 128);
            $table->string('scope_key', 1024);
            $table->primary(['entity_code', 'scope_key']);
        });
        Schema::create(Config::savedViewsTable(), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('entity_code', 128);
            $table->string('workspace_id', 255)->nullable();
            $table->string('owner_kind', 64)->nullable();
            $table->string('owner_id', 255)->nullable();
            $table->string('scope_key', 1024);
            $table->string('kind', 64);
            $table->jsonb('name');
            $table->jsonb('config');
            $table->string('schema_version', 128);
            $table->unsignedBigInteger('revision');
            $table->boolean('is_default');
            $table->string('default_scope_key', 1024)->nullable();
            $table->integer('position');
            $table->string('created_by', 128);
            $table->timestampsTz(precision: 6);
            $table->unique(['entity_code', 'default_scope_key']);
            $table->foreign(['entity_code', 'scope_key'])->references(['entity_code', 'scope_key'])->on(Config::savedViewScopesTable());
        });
    }
};
