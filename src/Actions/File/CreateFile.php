<?php

namespace Compose\Actions\File;

use Compose\Actions\Action;
use Compose\Enums\FileOperation;
use Compose\Execution\ActionResult;
use Compose\RecipeContext;

class CreateFile extends Action
{
    private ?string $originalContents = null;

    private bool $fileExisted = false;

    public function __construct(
        public readonly string $path,
        public readonly string $contents,
        public readonly bool $overwrite = true,
    ) {}

    public function type(): FileOperation
    {
        return FileOperation::Create;
    }

    public function execute(RecipeContext $context): ActionResult
    {
        $fullPath = $this->resolvePath($this->path);

        if (file_exists($fullPath)) {
            $this->fileExisted = true;

            if (! $this->overwrite) {
                return ActionResult::success(
                    command: $this->descriptionArray(),
                    output: "Skipped (exists): {$this->path}",
                );
            }

            $this->originalContents = file_get_contents($fullPath) ?: null;
        }

        $directory = dirname($fullPath);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true)) {
            return ActionResult::failure(
                errorOutput: "Failed to create directory: {$directory}",
                command: $this->descriptionArray(),
            );
        }

        if (file_put_contents($fullPath, $this->contents) === false) {
            return ActionResult::failure(
                errorOutput: "Failed to write file: {$this->path}",
                command: $this->descriptionArray(),
            );
        }

        return ActionResult::success(
            command: $this->descriptionArray(),
            output: "Created: {$this->path} (".strlen($this->contents).' bytes)',
        );
    }

    public function describe(): string
    {
        $size = strlen($this->contents);
        $preview = $size > 0 ? " ({$size} bytes)" : ' (empty)';

        return "create {$this->path}{$preview}";
    }

    #[\Override]
    public function canRollbackDirect(): bool
    {
        return true;
    }

    public function rollbackDirect(RecipeContext $context): ActionResult
    {
        $fullPath = $this->resolvePath($this->path);

        if ($this->fileExisted && $this->originalContents !== null) {
            file_put_contents($fullPath, $this->originalContents);

            return ActionResult::success(
                command: ['rollback:create', $this->path],
                output: "Restored: {$this->path}",
            );
        }

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        return ActionResult::success(
            command: ['rollback:create', $this->path],
            output: "Deleted: {$this->path}",
        );
    }

    /**
     * @return string[]
     */
    protected function descriptionArray(): array
    {
        return ['create', $this->path];
    }
}
