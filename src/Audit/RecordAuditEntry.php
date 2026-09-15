<?php

declare(strict_types=1);

namespace CoreX\Audit;

use CoreX\Contracts\AuditEntry;
use CoreX\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue worker that writes the sys_audit_log row (the sync path only
 * enqueues this job — see QueuedAuditLogger). occurred_at and workspace_id
 * are the EVENT-time snapshot carried on the AuditEntry: the worker only
 * writes them and NO LONGER resolves tenant here — an async worker runs under a
 * different (or absent) tenant context and would otherwise stamp a foreign
 * workspace and the write moment instead of the event moment.
 *
 * `$writeConnection` is null (the app's default) unless a caller pins an
 * explicit name — same seam as DatabaseRecordsRegistrar/
 * DatabaseSettingsRepository, used by the PG-only test lane
 * since sys_audit_log has no sqlite equivalent. Named apart from
 * `$connection` — Illuminate\Bus\Queueable already declares that property
 * for the job's own queue connection.
 *
 * @internal spec: B-10 §7.1, P1.6, P1.7, D12
 */
final class RecordAuditEntry implements ShouldQueueAfterCommit
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly AuditEntry $entry,
        private readonly ?string $writeConnection = null,
    ) {}

    public function handle(): void
    {
        AuditLog::on($this->writeConnection)->create([
            'occurred_at' => $this->entry->occurredAt ?? now(),
            'actor_type' => $this->entry->actorType,
            'actor_id' => $this->entry->actorId,
            'workspace_id' => $this->entry->workspaceId,
            'entity_type' => $this->entry->entityType,
            'entity_id' => $this->entry->entityId,
            'action' => $this->entry->action,
            'changes' => $this->entry->changes,
            'context' => $this->entry->context,
        ]);
    }
}
