<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Storage\ExportArchiveRef;
use CoreX\Storage\StorageCapabilities;
use Illuminate\Contracts\Filesystem\Filesystem;

interface TenantStorage
{
    public function disk(): Filesystem;

    public function ephemeralDisk(): Filesystem;

    public function capabilities(): StorageCapabilities;

    public function usageBytes(): int;

    public function exportArchive(): ExportArchiveRef;
}
