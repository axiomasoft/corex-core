<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * The five operations B-11 §5.9 п.3 forbids under an impersonated session
 * (L3 — semantic layer, for privileged paths that reach neither L1's HTTP
 * method check nor L2's Eloquent event). CLOSED vocabulary from the spec —
 * not a capability-key list (no such keys exist in the tree, D141/D150).
 *
 * @internal spec: B-11 §5.9 п.3, D141
 */
enum ImpersonationRestrictedAction
{
    case ChangePassword;
    case ChangeEmail;
    case Payment;
    case DeleteAccount;
    case GrantPrivilegedRole;
}
