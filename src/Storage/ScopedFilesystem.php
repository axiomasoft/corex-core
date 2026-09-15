<?php

declare(strict_types=1);

namespace CoreX\Storage;

use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final class ScopedFilesystem implements Filesystem
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly TenantContextResolver $contexts,
        private readonly string $accountId,
        private readonly string $root,
    ) {}

    public function path($path)
    {
        return $this->call('path', [$this->safePath($path)]);
    }

    public function exists($path)
    {
        return $this->call('exists', [$this->safePath($path)]);
    }

    public function get($path)
    {
        return $this->call('get', [$this->safePath($path)]);
    }

    public function readStream($path)
    {
        return $this->call('readStream', [$this->safePath($path)]);
    }

    public function put($path, $contents, $options = [])
    {
        return $this->call('put', [$this->safePath($path), $contents, $options]);
    }

    /**
     * @param  File|UploadedFile|string|array<mixed>|null  $file
     * @param  array<mixed>  $options
     */
    public function putFile($path, $file = null, $options = [])
    {
        if ($file === null || is_array($file)) {
            [$path, $file, $options] = ['', $path, $file ?? []];
        }

        return $this->call('putFile', [$this->safePath($path), $file, $options]);
    }

    /**
     * @param  File|UploadedFile|string|array<mixed>|null  $file
     * @param  string|array<mixed>|null  $name
     * @param  array<mixed>  $options
     */
    public function putFileAs($path, $file, $name = null, $options = [])
    {
        if ($name === null || is_array($name)) {
            [$path, $file, $name, $options] = ['', $path, $file, $name ?? []];
        }

        return $this->call('putFileAs', [$this->safePath($path), $file, $this->safePath($name), $options]);
    }

    /** @param array<mixed> $options */
    public function writeStream($path, $resource, array $options = [])
    {
        return $this->call('writeStream', [$this->safePath($path), $resource, $options]);
    }

    public function getVisibility($path)
    {
        return $this->call('getVisibility', [$this->safePath($path)]);
    }

    public function setVisibility($path, $visibility)
    {
        return $this->call('setVisibility', [$this->safePath($path), $visibility]);
    }

    public function prepend($path, $data)
    {
        return $this->call('prepend', [$this->safePath($path), $data]);
    }

    public function append($path, $data)
    {
        return $this->call('append', [$this->safePath($path), $data]);
    }

    /** @param string|array<mixed> $paths */
    public function delete($paths)
    {
        return $this->call('delete', [is_array($paths) ? array_map($this->safePath(...), $paths) : $this->safePath($paths)]);
    }

    public function copy($from, $to)
    {
        return $this->call('copy', [$this->safePath($from), $this->safePath($to)]);
    }

    public function move($from, $to)
    {
        return $this->call('move', [$this->safePath($from), $this->safePath($to)]);
    }

    public function size($path)
    {
        return $this->call('size', [$this->safePath($path)]);
    }

    public function lastModified($path)
    {
        return $this->call('lastModified', [$this->safePath($path)]);
    }

    public function files($directory = null, $recursive = false)
    {
        return $this->call('files', [$directory === null ? null : $this->safePath($directory), $recursive]);
    }

    public function allFiles($directory = null)
    {
        return $this->call('allFiles', [$directory === null ? null : $this->safePath($directory)]);
    }

    public function directories($directory = null, $recursive = false)
    {
        return $this->call('directories', [$directory === null ? null : $this->safePath($directory), $recursive]);
    }

    public function allDirectories($directory = null)
    {
        return $this->call('allDirectories', [$directory === null ? null : $this->safePath($directory)]);
    }

    public function makeDirectory($path)
    {
        return $this->call('makeDirectory', [$this->safePath($path)]);
    }

    public function deleteDirectory($directory)
    {
        return $this->call('deleteDirectory', [$this->safePath($directory)]);
    }

    public function url(string $path): string
    {
        $this->safePath($path);

        throw new StorageOperationFailed('The local scoped storage driver does not expose file URLs.');
    }

    /** @param array<mixed> $arguments */
    private function call(string $method, array $arguments): mixed
    {
        if ($this->contexts->current()->account->id !== $this->accountId) {
            throw new StorageOperationFailed('Storage handle belongs to a different account context.');
        }

        try {
            return $this->filesystem->{$method}(...$arguments);
        } catch (RuntimeException $exception) {
            throw new StorageOperationFailed('Scoped storage operation failed.', previous: $exception);
        }
    }

    private function safePath(mixed $path): string
    {
        if (! is_string($path) || str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new StorageOperationFailed('Storage path is invalid.');
        }

        $decoded = $path;
        do {
            $previous = $decoded;
            $decoded = rawurldecode($decoded);
        } while ($decoded !== $previous);

        if (str_starts_with($decoded, '/') || preg_match('/^[A-Za-z]:/', $decoded) === 1) {
            throw new StorageOperationFailed('Storage path must be relative.');
        }

        foreach (explode('/', $decoded) as $segment) {
            if ($segment === '..') {
                throw new StorageOperationFailed('Storage path traversal is forbidden.');
            }
        }

        $this->assertNoSymlink(path: $decoded);

        return $decoded;
    }

    private function assertNoSymlink(string $path): void
    {
        $candidate = rtrim($this->root, DIRECTORY_SEPARATOR);

        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }

            $candidate .= DIRECTORY_SEPARATOR.$segment;

            if (is_link($candidate)) {
                throw new StorageOperationFailed('Storage paths must not contain symbolic links.');
            }
        }
    }
}
