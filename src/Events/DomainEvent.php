<?php

declare(strict_types=1);

namespace CoreX\Events;

use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Canonical base for ecosystem domain events (eventId/occurredAt/tenant/
 * `name()`). Supersedes the empty `CoreEvent` marker —
 * `Cancellable` is a SEPARATE optional interface for pre-action events that
 * can abort the operation ({@see Cancellable}), not mixed into this base:
 * most domain events (SettingChanged, FeatureFlagToggled, …) are purely
 * informational.
 *
 * @internal spec: B-10 §3.1, D27
 */
abstract class DomainEvent
{
    public readonly string $eventId;

    public readonly DateTimeImmutable $occurredAt;

    public readonly TenantContext $tenant;

    public function __construct(?TenantContext $tenant = null)
    {
        $this->eventId = (string) Str::uuid7();
        $this->occurredAt = new DateTimeImmutable;
        $this->tenant = $tenant ?? app(TenantContextResolver::class)->current();
    }

    /**
     * Stable dotted name, e.g. `core.setting.changed`.
     *
     * @internal spec: B-10 domain-events table
     */
    abstract public static function name(): string;
}
