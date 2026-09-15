<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * Who performed an audited change (sys_audit_log CHECK constraint).
 *
 * `Support` (D137) — a staff identity acting under B-11 §5.9 impersonation;
 * added additively (`sys_audit_actor_ck`/`mod_ll_actor_ck` both widened by
 * new migrations, never by editing the closed P1.8/P1.3 files).
 *
 * @internal spec: B-10 §2, D137
 */
enum ActorType: string
{
    case User = 'user';
    case Agent = 'agent';
    case ApiKey = 'api_key';
    case System = 'system';
    case Support = 'support';
}
