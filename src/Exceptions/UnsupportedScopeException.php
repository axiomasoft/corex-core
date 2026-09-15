<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use CoreX\Enums\SettingScope;
use RuntimeException;

/**
 * `sys_settings`'s CHECK constraint accepts only account/workspace/user —
 * `SettingsScope::department()` is a `FeatureFlags`-only override level
 * (`sys_feature_overrides` CHECK already allows it). A caller handing
 * a department scope to `SettingsRepository` gets this instead of a silent
 * account-level write.
 *
 * @internal spec: D38
 */
final class UnsupportedScopeException extends RuntimeException
{
    public function __construct(SettingScope $scope)
    {
        parent::__construct(sprintf(
            'SettingsRepository does not support scope "%s" — sys_settings only accepts account/workspace/user (D38).',
            $scope->value,
        ));
    }
}
