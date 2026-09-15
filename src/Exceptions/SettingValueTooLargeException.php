<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use RuntimeException;

/**
 * Quota: `sys_settings.value` ≤ 64 KB encoded.
 *
 * @internal spec: B-10 §7.2
 */
final class SettingValueTooLargeException extends RuntimeException
{
    public function __construct(string $namespace, string $key, int $bytes, int $limit)
    {
        parent::__construct(sprintf(
            'Setting %s.%s value is %d bytes, exceeding the %d byte quota (B-10 §7.2).',
            $namespace,
            $key,
            $bytes,
            $limit,
        ));
    }
}
