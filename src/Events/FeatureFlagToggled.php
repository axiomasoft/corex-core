<?php

declare(strict_types=1);

namespace CoreX\Events;

use CoreX\Contracts\SettingsScope;
use Override;

/**
 * Domain-event shape for a feature-flag state change
 * (`core.feature.toggled`). `FeatureFlags` ships no toggle-mutator itself —
 * `define()` only registers a manifest default — so no production code path
 * dispatches this yet; the admin toggle action that will (UI feature-flag
 * management is out of scope here) is deferred to future work.
 *
 * @internal spec: B-10, P1.9
 */
final class FeatureFlagToggled extends DomainEvent
{
    public function __construct(
        public readonly string $key,
        public readonly ?SettingsScope $scope,
        public readonly bool $isEnabled,
    ) {
        parent::__construct();
    }

    #[Override]
    public static function name(): string
    {
        return 'core.feature.toggled';
    }
}
