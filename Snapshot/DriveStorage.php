<?php

namespace Omnimap\Snapshot;

use Omnidrive\Drive;
use Omnidrive\Exception\FileNotFoundException;

/**
 * The cache on an omnidrive Drive (omniscient/omnidrive, suggested): the
 * storage an application names ("uploads"), wherever it is - a directory, an
 * S3 bucket, an SFTP server - Flysystem behind it.
 *
 * @suggested omniscient/omnidrive
 */
final class DriveStorage implements Storage
{
    public function __construct(private readonly Drive $drive)
    {
    }

    public function read(string $path): ?string
    {
        try {
            return $this->drive->read($path);
        } catch (FileNotFoundException) {
            return null;
        }
    }

    public function write(string $path, string $contents): void
    {
        $this->drive->write($path, $contents);
    }

    public function exists(string $path): bool
    {
        return $this->drive->exists($path);
    }

    public function deleteDirectory(string $path): void
    {
        if ($this->drive->directoryExists($path)) {
            $this->drive->deleteDirectory($path);
        }
    }
}
