<?php

declare(strict_types=1);

namespace CoreX\Settings;

use CoreX\Contracts\SettingDefaultsProvider;
use Override;

/**
 * Boxed-default: no manifest registry wired in — every key is undeclared
 * (no default) and non-sensitive.
 *
 * @internal spec: D10
 */
final class NullSettingDefaultsProvider implements SettingDefaultsProvider
{
    #[Override]
    public function defaultFor(string $namespace, string $key): mixed
    {
        return null;
    }

    #[Override]
    public function isSensitive(string $namespace, string $key): bool
    {
        return false;
    }
}
