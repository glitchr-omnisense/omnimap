<?php

namespace Omnimap\Snapshot;

use Omnimap\Exception\InvalidArgumentException;

/** The cache in a directory of the server's disk. */
final class DirectoryStorage implements Storage
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
    }

    public function read(string $path): ?string
    {
        $file = $this->file($path);

        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    public function write(string $path, string $contents): void
    {
        $file = $this->file($path);
        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0775, true);
        }
        file_put_contents($file, $contents);
    }

    public function exists(string $path): bool
    {
        return is_file($this->file($path));
    }

    public function deleteDirectory(string $path): void
    {
        $directory = $this->file($path);
        if (!is_dir($directory)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
    }

    private function file(string $path): string
    {
        if (str_contains('/'.$path.'/', '/../')) {
            throw new InvalidArgumentException(\sprintf('No ".." in a cache path: "%s".', $path));
        }

        return $this->root.'/'.ltrim($path, '/');
    }
}
