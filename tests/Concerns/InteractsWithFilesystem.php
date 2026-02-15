<?php

namespace Compose\Tests\Concerns;

trait InteractsWithFilesystem
{
    protected string $tempPath;

    protected function initializeTempDirectory(): void
    {
        $this->tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'compose_' . uniqid();

        mkdir($this->tempPath, 0755, true);
    }

    protected function cleanupTempDirectory(): void
    {
        if (! isset($this->tempPath) || ! is_dir($this->tempPath)) {
            return;
        }

        $this->deleteDirectory($this->tempPath);
    }

    protected function tempPath(string $path = ''): string
    {
        return $this->tempPath . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }

    protected function createFile(string $path, string $contents = ''): string
    {
        $fullPath = $this->tempPath($path);

        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($fullPath, $contents);

        return $fullPath;
    }

    private function deleteDirectory(string $directory): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($directory);
    }
}
