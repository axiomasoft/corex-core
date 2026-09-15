<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * Settings cascade level: user overrides workspace overrides
 * account overrides the manifest-declared default. `Department` is a
 * `FeatureFlags`-only override level — `sys_settings`'s CHECK constraint
 * still accepts only account/workspace/user; `DatabaseSettingsRepository`
 * throws `UnsupportedScopeException` if asked to read/write it.
 *
 * @internal spec: B-10 §3.1, D38
 */
enum SettingScope: string
{
    case Account = 'account';
    case Workspace = 'workspace';
    case User = 'user';
    case Department = 'department';
}
