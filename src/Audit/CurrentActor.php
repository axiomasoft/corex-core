<?php

declare(strict_types=1);

namespace CoreX\Audit;

use CoreX\Enums\ActorType;
use CoreX\Tenancy\Contracts\ImpersonationService;
use CoreX\Tenancy\ImpersonationState;

/**
 * Actor/request snapshot for an audit entry built outside a queue worker
 * (context: ip/user_agent/request_id/module). Real agent/api_key actor
 * wiring lands with entity-scoped auth. Under an active impersonation
 * (D137/§5.9 п.3) the actor is `support`/staff — resolved from the
 * `ImpersonationService` CONTRACT via the container, never a `corex/tenancy`
 * import (D139 — this class lives in ядро, level 0 must not depend on the
 * cloud package).
 *
 * @internal spec: B-10 §3.1, P2, D137/D139
 */
final class CurrentActor
{
    public static function type(): ActorType
    {
        if (self::state() !== null) {
            return ActorType::Support;
        }

        return auth()->check() ? ActorType::User : ActorType::System;
    }

    public static function id(): ?string
    {
        $state = self::state();

        if ($state !== null) {
            return $state->staffIdentityId;
        }

        $user = auth()->user();

        return $user !== null ? (string) $user->getAuthIdentifier() : null;
    }

    /** @return array<string, mixed> */
    public static function context(): array
    {
        $context = [];

        $state = self::state();

        if ($state !== null) {
            $context['impersonation'] = [
                'grant' => $state->grantId,
                'target_user_id' => $state->targetUserId,
            ];
        }

        if (app()->runningInConsole() || ! app()->bound('request')) {
            return $context;
        }

        $request = request();

        return array_filter([
            ...$context,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-Id'),
        ], static fn (mixed $value): bool => $value !== null);
    }

    private static function state(): ?ImpersonationState
    {
        return app(ImpersonationService::class)->state();
    }
}
