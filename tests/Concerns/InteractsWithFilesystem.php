<?php

namespace Compose\Tests\Concerns;

use Compose\Filesystem;

trait InteractsWithFilesystem
{
    protected string $tempPath;

    protected function initializeTempDirectory(): void
    {
        $this->tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'compose_'.uniqid();

        mkdir($this->tempPath, 0755, true);
    }

    protected function cleanupTempDirectory(): void
    {
        if (! isset($this->tempPath) || ! is_dir($this->tempPath)) {
            return;
        }

        Filesystem::deleteDirectory($this->tempPath);
    }

    protected function tempPath(string $path = ''): string
    {
        return $this->tempPath.($path ? DIRECTORY_SEPARATOR.ltrim($path, '/\\') : '');
    }

    protected function createFile(string $path, string $contents = ''): string
    {
        $fullPath = $this->tempPath($path);

        $directory = dirname((string) $fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($fullPath, $contents);

        return $fullPath;
    }
}
