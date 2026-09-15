<?php

declare(strict_types=1);

namespace CoreX\Concerns;

use CoreX\Enums\IdStrategy;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Config-driven primary key: UUIDv7 (default, per ADR-003), UUID, ULID, or
 * auto-increment int — per config('corex.ids.strategy'). A single seam so a
 * host app can line CoreX models up with its own key type without forking
 * the package (mirrors AzGuard's configurable morph key). Pair with
 * CoreX\Support\Schema\IdColumns in migrations so the column type matches.
 *
 * @internal spec: D3
 */
trait HasConfiguredId
{
    public static function bootHasConfiguredId(): void
    {
        static::creating(function (Model $model): void {
            if (Config::idStrategy() === IdStrategy::Int) {
                return;
            }

            foreach ($model->uniqueIds() as $column) {
                if (empty($model->getAttribute($column))) {
                    $model->setAttribute($column, $model->newUniqueId());
                }
            }
        });
    }

    /**
     * Columns that receive a generated id. Empty for the int strategy
     * (the database auto-increments).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return Config::idStrategy() === IdStrategy::Int ? [] : [$this->getKeyName()];
    }

    public function newUniqueId(): string
    {
        return match (Config::idStrategy()) {
            IdStrategy::Uuid7 => (string) Str::uuid7(),
            IdStrategy::Uuid => (string) Str::orderedUuid(),
            default => (string) Str::ulid(),
        };
    }

    public function getKeyType(): string
    {
        return Config::idStrategy() === IdStrategy::Int ? 'int' : 'string';
    }

    public function getIncrementing(): bool
    {
        return Config::idStrategy() === IdStrategy::Int;
    }
}
