<?php

declare(strict_types=1);

namespace CoreX\Concerns;

use CoreX\Audit\AuditObserver;
use CoreX\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Records an append-only change history via AuditObserver::record() (single
 * write path shared with the EntityRegistry-driven auto-observer, see
 * AuditObserver). Manual opt-in for models outside EntityRegistry (e.g. a
 * host app's User model); the model must also implement Auditable.
 *
 * @mixin Model
 */
trait HasAuditLog
{
    public static function bootHasAuditLog(): void
    {
        // Observers are registered UNCONDITIONALLY — the killswitch
        // (Config::auditEnabled) is evaluated at write time in
        // AuditObserver::record(), not here (A60): model boot happens once and
        // is cached across the process, so a boot-time skip would never
        // re-enable when the config flips (and the capability path never saw
        // the flag at all).
        static::created(static fn (Model $model): null => self::recordAudit($model, 'created'));
        static::updated(static fn (Model $model): null => self::recordAudit($model, 'updated'));
        static::deleted(static fn (Model $model): null => self::recordAudit($model, 'deleted'));

        // restored/forceDeleted registrars live in the SoftDeletes trait, not
        // on the base Model — register through registerModelEvent so HasAuditLog
        // stays usable on non-SoftDeletes models too (the events simply never
        // fire there). Both are meaningful only under SoftDeletes.
        static::registerModelEvent('restored', static fn (Model $model): null => self::recordAudit($model, 'restored'));
        static::registerModelEvent('forceDeleted', static fn (Model $model): null => self::recordAudit($model, 'forceDeleted'));
    }

    /**
     * Attributes excluded from the audit diff.
     *
     * @return list<string>
     */
    public function auditExcept(): array
    {
        return ['created_at', 'updated_at'];
    }

    /**
     * Attributes masked in the audit diff.
     *
     * @return list<string>
     */
    public function auditSensitive(): array
    {
        return [];
    }

    private static function recordAudit(Model $model, string $action): null
    {
        if ($model instanceof Auditable) {
            AuditObserver::record($model, $action);
        }

        return null;
    }
}
