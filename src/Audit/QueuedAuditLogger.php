<?php

declare(strict_types=1);

namespace CoreX\Audit;

use CoreX\Contracts\AuditEntry;
use CoreX\Contracts\AuditLogger;
use CoreX\Support\Config;
use CoreX\Tenancy\Contracts\TenantContextResolver;

/**
 * Synchronous enqueue path (the sync-path budget only dispatches; the row
 * is written by RecordAuditEntry on a worker).
 *
 * The event-time snapshot is taken HERE: occurredAt = now() and
 * workspaceId = the CURRENT tenant context — both captured while the request
 * that produced the event is still alive. The worker only writes them; it no
 * longer resolves tenant at write time (an async worker runs under a different
 * or absent tenant context and would stamp a foreign workspace and the write
 * moment). Boxed/CLI installs (SingleAccountResolver) carry workspaceId = null
 * — an honest null, not a foreign tenant.
 *
 * `$connection` is null (the app's default) unless a caller pins an explicit
 * name — forwarded to RecordAuditEntry, same seam as DatabaseSettingsRepository,
 * used by the PG-only test lane. `$tenantContext` is optional so
 * a caller can construct the logger directly (test lane); when null it is
 * resolved from the container at record time.
 *
 * @internal spec: B-10 §7.1, P1.7, D12
 */
final class QueuedAuditLogger implements AuditLogger
{
    public function __construct(
        private readonly ?string $connection = null,
        private readonly ?TenantContextResolver $tenantContext = null,
    ) {}

    public function record(AuditEntry $entry): void
    {
        // Killswitch, re-checked at the WRITE seam (defense-in-depth):
        // AuditObserver::record() already guards, but this is
        // the AuditLogger contract — any caller resolving it directly (not via
        // the observer) would otherwise enqueue a row with audit disabled. The
        // gate lives at enqueue time, not in RecordAuditEntry::handle(): an
        // event captured while auditing was ON must still land if config flips
        // before the worker runs (the journal records what happened).
        if (! Config::auditEnabled()) {
            return;
        }

        $resolver = $this->tenantContext ?? app(TenantContextResolver::class);

        RecordAuditEntry::dispatch(
            $entry->withSnapshot(
                occurredAt: now()->toImmutable(),
                workspaceId: $resolver->current()->workspace?->id,
            ),
            $this->connection,
        );
    }
}
