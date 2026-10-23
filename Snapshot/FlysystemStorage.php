<?php

namespace Omnimap\Snapshot;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;

/**
 * The cache in a Flysystem storage (league/flysystem 3, suggested): local,
 * S3, SFTP... - the operator a host already has (Symfony's flysystem-bundle
 * storages, a Laravel disk's driver).
 *
 * @suggested league/flysystem
 */
final class FlysystemStorage implements Storage
{
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    public function read(string $path): ?string
    {
        try {
            return $this->filesystem->read($path);
        } catch (UnableToReadFile) {
            return null;
        }
    }

    /** @throws FilesystemException */
    public function write(string $path, string $contents): void
    {
        $this->filesystem->write($path, $contents);
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->fileExists($path);
    }

    /** @throws FilesystemException */
    public function deleteDirectory(string $path): void
    {
        $this->filesystem->deleteDirectory($path);
    }
}
