<?php

declare(strict_types=1);

namespace CoreX\Support\Data;

use CoreX\Tenancy\TenantContext;
use InvalidArgumentException;

final readonly class DataScope
{
    public function __construct(
        public TenantContext $context,
        public string $connectionIdentity,
        public string $actorId,
        public string $restoreEpoch,
    ) {
        self::assertOpaque(value: $connectionIdentity, name: 'connection identity');
        self::assertOpaque(value: $actorId, name: 'actor identity');

        if (preg_match('/\\A[a-f0-9]{64}\\z/D', $restoreEpoch) !== 1) {
            throw new InvalidArgumentException('Restore epoch must be a lowercase SHA-256 digest.');
        }
    }

    private static function assertOpaque(string $value, string $name): void
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/[\\x00-\\x1f\\x7f]/', $value) === 1) {
            throw new InvalidArgumentException("Invalid {$name}.");
        }
    }
}
