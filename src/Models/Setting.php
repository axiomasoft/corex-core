<?php

declare(strict_types=1);

namespace CoreX\Models;

use CoreX\Concerns\HasConfiguredId;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property string $namespace
 * @property string $key
 * @property string $value
 * @property string $scope_type
 * @property string|null $scope_id
 * @property bool $is_sensitive
 */
final class Setting extends Model
{
    use HasConfiguredId;

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'is_sensitive' => 'bool',
    ];

    #[Override]
    public function getTable(): string
    {
        return Config::settingsTable();
    }
}
