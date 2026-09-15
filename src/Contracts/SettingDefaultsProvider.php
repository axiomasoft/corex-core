<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Settings\NullSettingDefaultsProvider;

/**
 * Manifest-declared setting defaults, injected so corex/core never imports
 * `CoreX\Modules\SettingDefault` (direction core←modules). The boxed default
 * is a null object ({@see NullSettingDefaultsProvider});
 * ModulesServiceProvider (corex/modules) wires the real implementation over
 * the compiled manifest registry.
 */
interface SettingDefaultsProvider
{
    public function defaultFor(string $namespace, string $key): mixed;

    public function isSensitive(string $namespace, string $key): bool;
}
