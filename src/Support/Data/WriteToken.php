<?php

declare(strict_types=1);

namespace CoreX\Support\Data;

use InvalidArgumentException;

final readonly class WriteToken
{
    public function __construct(
        public string $operationKey,
        public string $payloadHash,
        public string $expectedRecordVersion,
    ) {
        self::assertOpaque(value: $operationKey, name: 'operation key');

        if (preg_match('/\\A[a-f0-9]{64}\\z/D', $payloadHash) !== 1) {
            throw new InvalidArgumentException('Payload hash must be a lowercase SHA-256 digest.');
        }

        self::assertOpaque(value: $expectedRecordVersion, name: 'expected record version');
    }

    private static function assertOpaque(string $value, string $name): void
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/[\\x00-\\x1f\\x7f]/', $value) === 1) {
            throw new InvalidArgumentException("Invalid {$name}.");
        }
    }
}
