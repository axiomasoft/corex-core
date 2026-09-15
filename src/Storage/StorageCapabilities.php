<?php

declare(strict_types=1);

namespace CoreX\Storage;

final readonly class StorageCapabilities
{
    public function __construct(
        public bool $usage,
        public bool $export,
    ) {}
}
