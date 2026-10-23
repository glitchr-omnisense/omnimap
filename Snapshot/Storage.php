<?php

namespace Omnimap\Snapshot;

/**
 * Where the map pictures are kept: the four things the cache asks of a
 * storage. FlysystemStorage puts any Flysystem storage behind it (local,
 * S3...), DirectoryStorage a plain directory; an application's own storage
 * contract fits in a few lines.
 *
 * Paths are relative, "/"-separated: "google/<signature>/image-0.png".
 */
interface Storage
{
    /** The file's contents; null when there is no such file. */
    public function read(string $path): ?string;

    public function write(string $path, string $contents): void;

    public function exists(string $path): bool;

    /** The directory and everything in it; nothing when it is not there. */
    public function deleteDirectory(string $path): void;
}
