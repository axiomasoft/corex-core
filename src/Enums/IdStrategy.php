<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * Primary-key strategy for BaseModel. UUIDv7 is the default (ADR-003 revision);
 * Ulid/Uuid (v4)/Int seams remain for boxed/legacy installs.
 */
enum IdStrategy: string
{
    case Ulid = 'ulid';
    case Uuid = 'uuid';
    case Uuid7 = 'uuid7';
    case Int = 'int';
}
