<?php

declare(strict_types=1);

namespace CoreX\Events;

use CoreX\Enums\SettingScope;
use Override;

/**
 * Fired by `SettingsRepository::set()` ONLY when the stored value actually
 * changes, and only after the write's transaction commits
 * (`DatabaseSettingsRepository::set()`) — a no-op `set()` of an unchanged
 * value does not spam listeners, and a rolled-back write never fires it.
 * `old`/`new` are masked when `sensitive` is true — this event never
 * carries a sensitive value in the clear, including to queued listeners'
 * logs.
 *
 * Retrofitted onto `DomainEvent` — was interim `CoreEvent` before that.
 *
 * @internal spec: B-10 §3.1, §7.4 п.8, D38, P1.22, P1.9, OQ-4, P1.7
 */
final class SettingChanged extends DomainEvent
{
    private const MASK = '••••••';

    public readonly mixed $old;

    public readonly mixed $new;

    public function __construct(
        public readonly string $namespace,
        public readonly string $key,
        public readonly SettingScope $scopeType,
        public readonly ?string $scopeId,
        mixed $old,
        mixed $new,
        public readonly bool $sensitive,
    ) {
        parent::__construct();
        $this->old = $this->sensitive ? self::MASK : $old;
        $this->new = $this->sensitive ? self::MASK : $new;
    }

    #[Override]
    public static function name(): string
    {
        return 'core.setting.changed';
    }
}
