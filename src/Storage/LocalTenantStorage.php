<?php

declare(strict_types=1);

namespace CoreX\Storage;

use CoreX\Contracts\TenantStorage;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class LocalTenantStorage implements TenantStorage
{
    public function __construct(private readonly TenantContextResolver $contexts, private readonly FilesystemManager $filesystems, private readonly string $durableRoot, private readonly string $ephemeralRoot) {}

    public function disk(): Filesystem
    {
        return $this->scopedDisk(root: $this->durableRoot);
    }

    public function ephemeralDisk(): Filesystem
    {
        return $this->scopedDisk(root: $this->ephemeralRoot);
    }

    public function capabilities(): StorageCapabilities
    {
        return new StorageCapabilities(usage: true, export: false);
    }

    public function usageBytes(): int
    {
        $path = $this->tenantRoot(root: $this->durableRoot);

        if (! is_dir($path)) {
            return 0;
        }
        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(directory: $path, flags: RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new StorageOperationFailed('Storage root contains a symbolic link.');
            }

            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    public function exportArchive(): ExportArchiveRef
    {
        throw new StorageCapabilityUnavailable('Storage export is unavailable for the local driver.');
    }

    private function scopedDisk(string $root): ScopedFilesystem
    {
        $context = $this->contexts->current();

        return new ScopedFilesystem(
            filesystem: $this->filesystems->build(config: ['driver' => 'local', 'root' => $this->tenantRoot(root: $root, accountId: $context->account->id), 'visibility' => 'private', 'links' => 'disallow']),
            contexts: $this->contexts,
            accountId: $context->account->id,
            root: $this->tenantRoot(root: $root, accountId: $context->account->id),
        );
    }

    private function tenantRoot(string $root, ?string $accountId = null): string
    {
        $accountId ??= $this->contexts->current()->account->id;

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $accountId) !== 1) {
            throw new StorageOperationFailed('Tenant account identifier is invalid.');
        }

        return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'tenants'.DIRECTORY_SEPARATOR.$accountId;
    }
}
