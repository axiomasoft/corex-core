<?php

declare(strict_types=1);

namespace CoreX\Models;

use CoreX\Concerns\HasConfiguredId;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Per-account feature-flag default (sys_feature_flags).
 *
 * @property string $id
 * @property string $key
 * @property string|null $module
 * @property bool $is_enabled
 * @property array<string, mixed>|null $payload
 *
 * @internal spec: B-10 §2
 */
final class FeatureFlag extends Model
{
    use HasConfiguredId;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'bool',
            'payload' => 'array',
        ];
    }

    #[Override]
    public function getTable(): string
    {
        return Config::featureFlagsTable();
    }
}
