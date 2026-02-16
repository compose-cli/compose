<?php

namespace Compose;

class Filesystem
{
    /**
     * Recursively delete a directory and all its contents.
     *
     * Handles read-only files (e.g. Git pack files on Windows)
     * by clearing the read-only attribute before unlinking.
     */
    public static function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $path = $item->getRealPath();

            if ($item->isDir()) {
                rmdir($path);
            } else {
                chmod($path, 0666);
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
