<?php

declare(strict_types=1);

namespace CoreX\Storage;

use DateTimeImmutable;

final readonly class ExportArchiveRef
{
    public function __construct(
        public string $storagePath,
        public int $sizeBytes,
        public DateTimeImmutable $createdAt,
    ) {}
}
