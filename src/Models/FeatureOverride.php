<?php

declare(strict_types=1);

namespace CoreX\Models;

use CoreX\Concerns\HasConfiguredId;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Workspace/user/department override of a {@see FeatureFlag}
 * (sys_feature_overrides). `flag_id` cascades on flag delete (FK ON DELETE
 * CASCADE).
 *
 * @property string $id
 * @property string $flag_id
 * @property string $scope_type
 * @property string $scope_id
 * @property bool $is_enabled
 * @property array<string, mixed>|null $payload
 *
 * @internal spec: B-10 §2
 */
final class FeatureOverride extends Model
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
        return Config::featureOverridesTable();
    }
}
