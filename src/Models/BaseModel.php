<?php

declare(strict_types=1);

namespace CoreX\Models;

use CoreX\Concerns\HasAuditLog;
use CoreX\Concerns\HasConfiguredId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base for ecosystem domain models: ULID key (ADR-003), soft deletes, and an
 * audit trail that only records when the model also implements Auditable.
 *
 * Tenant isolation is physical (DB-per-account, B-11): a model loaded here
 * already comes from its account's database, so no row-level scope is applied.
 * Models that cannot extend this base (e.g. an Authenticatable User) compose
 * the same traits directly.
 */
abstract class BaseModel extends Model
{
    use HasAuditLog;
    use HasConfiguredId;
    use SoftDeletes;
}
