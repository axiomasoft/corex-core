<?php

declare(strict_types=1);

namespace CoreX\Models;

use CoreX\Concerns\HasConfiguredId;
use CoreX\Enums\ActorType;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * Append-only audit entry (sys_audit_log). No updated_at:
 * DB_SCHEMA.md §Конфликт-2 (resolved 2026-07-13) — an append-only class
 * keeps updated_at only if it has ≥1 legitimate post-insert field; this
 * table has none.
 *
 * @property string $id
 * @property Carbon $occurred_at
 * @property ActorType $actor_type
 * @property string|null $actor_id
 * @property string|null $workspace_id
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property string $action
 * @property array<string, mixed>|null $changes
 * @property array<string, mixed> $context
 *
 * @internal spec: B-10 §2
 */
final class AuditLog extends Model
{
    use HasConfiguredId;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'actor_type' => ActorType::class,
            'changes' => 'array',
            'context' => 'array',
        ];
    }

    #[Override]
    public function getTable(): string
    {
        return Config::auditLogsTable();
    }
}
