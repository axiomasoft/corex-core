<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Enums\ActorType;
use DateTimeImmutable;

/**
 * One append-only audit record. Built by the caller (HasAuditLog trait or
 * CoreX\Audit\AuditObserver) and handed to AuditLogger::record().
 *
 * `occurredAt`/`workspaceId` are the event-time snapshot: the synchronous
 * path (QueuedAuditLogger::record) stamps them via withSnapshot() while the
 * request's tenant context is still alive — the queue worker only WRITES
 * them, it no longer resolves tenant at write time (an async worker runs
 * under a different — or absent — tenant context and would otherwise stamp a
 * FOREIGN workspace and the write time instead of the event time). The `id`
 * column stays filled at write time (DB-generated).
 *
 * @internal spec: B-10 §3.1
 */
final readonly class AuditEntry
{
    /**
     * @param  array<string, array{old: mixed, new: mixed}>|null  $changes
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $action,
        public ActorType $actorType,
        public ?string $actorId = null,
        public ?string $entityType = null,
        public ?string $entityId = null,
        public ?array $changes = null,
        public array $context = [],
        public ?DateTimeImmutable $occurredAt = null,
        public ?string $workspaceId = null,
    ) {}

    /**
     * Return a copy carrying the event-time snapshot (occurredAt + workspaceId).
     * Stamped on the synchronous enqueue path so the worker writes the moment
     * and tenant of the EVENT, not of the write.
     */
    public function withSnapshot(DateTimeImmutable $occurredAt, ?string $workspaceId): self
    {
        return new self(
            action: $this->action,
            actorType: $this->actorType,
            actorId: $this->actorId,
            entityType: $this->entityType,
            entityId: $this->entityId,
            changes: $this->changes,
            context: $this->context,
            occurredAt: $occurredAt,
            workspaceId: $workspaceId,
        );
    }
}
