<?php

declare(strict_types=1);

namespace CoreX\Support\Schema;

use CoreX\Enums\IdStrategy;
use CoreX\Support\Config;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\ForeignIdColumnDefinition;

/**
 * Key columns whose type follows config('corex.ids.strategy'): UUIDv7
 * (default), UUID, ULID, or auto-increment int. Lets a host app line
 * CoreX tables up with its own key type without forking the package
 * migrations (mirrors AzGuard's MorphColumns). Pair with
 * CoreX\Concerns\HasConfiguredId on the models.
 *
 * @internal spec: D3
 */
final class IdColumns
{
    /**
     * Primary key column.
     */
    public static function primary(Blueprint $table, string $name = 'id'): void
    {
        match (Config::idStrategy()) {
            IdStrategy::Int => $table->id($name),
            IdStrategy::Uuid, IdStrategy::Uuid7 => $table->uuid($name)->primary(),
            IdStrategy::Ulid => $table->ulid($name)->primary(),
        };
    }

    /**
     * A loose reference column (no DB foreign-key constraint), e.g. tenant_id,
     * owner_id. Chain ->nullable()->index() at the call site as needed.
     */
    public static function reference(Blueprint $table, string $name): ColumnDefinition
    {
        return match (Config::idStrategy()) {
            IdStrategy::Int => $table->unsignedBigInteger($name),
            IdStrategy::Uuid, IdStrategy::Uuid7 => $table->uuid($name),
            IdStrategy::Ulid => $table->ulid($name),
        };
    }

    /**
     * A constrained foreign key. Chain ->constrained(...)->cascadeOnDelete()
     * (and ->nullable()) at the call site.
     */
    public static function foreign(Blueprint $table, string $name): ForeignIdColumnDefinition
    {
        return match (Config::idStrategy()) {
            IdStrategy::Int => $table->foreignId($name),
            IdStrategy::Uuid, IdStrategy::Uuid7 => $table->foreignUuid($name),
            IdStrategy::Ulid => $table->foreignUlid($name),
        };
    }
}
