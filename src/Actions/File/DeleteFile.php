<?php

namespace Compose\Actions\File;

use Compose\Actions\Action;
use Compose\Enums\FileOperation;
use Compose\Execution\ActionResult;
use Compose\Filesystem;
use Compose\RecipeContext;

class DeleteFile extends Action
{
    /**
     * @var string[]
     */
    public readonly array $paths;

    public function __construct(string ...$paths)
    {
        $this->paths = $paths;
    }

    public function type(): FileOperation
    {
        return FileOperation::Delete;
    }

    public function execute(RecipeContext $context): ActionResult
    {
        $deleted = [];
        $skipped = [];

        foreach ($this->paths as $path) {
            $fullPath = $this->resolvePath($path);

            // Support glob patterns
            $matches = glob($fullPath);

            if ($matches === false || $matches === []) {
                $skipped[] = $path;

                continue;
            }

            foreach ($matches as $match) {
                if (is_dir($match)) {
                    Filesystem::deleteDirectory($match);
                    $deleted[] = $this->relativePath($match);
                } elseif (is_file($match)) {
                    unlink($match);
                    $deleted[] = $this->relativePath($match);
                }
            }
        }

        $output = '';

        if ($deleted !== []) {
            $output .= 'Deleted: '.implode(', ', $deleted);
        }

        if ($skipped !== []) {
            $output .= ($output !== '' ? '; ' : '').'Not found: '.implode(', ', $skipped);
        }

        return ActionResult::success(
            command: $this->descriptionArray(),
            output: $output ?: 'Nothing to delete',
        );
    }

    public function describe(): string
    {
        return 'delete '.implode(', ', $this->paths);
    }

    /**
     * Try to make a resolved path relative for display.
     */
    protected function relativePath(string $fullPath): string
    {
        $cwd = $this->context()->workingDirectory;

        if ($cwd !== null && str_starts_with($fullPath, $cwd)) {
            return ltrim(substr($fullPath, strlen($cwd)), '/\\');
        }

        return $fullPath;
    }

    /**
     * @return string[]
     */
    protected function descriptionArray(): array
    {
        return ['delete', ...$this->paths];
    }
}
