<?php

namespace Compose\Actions\File;

use Compose\Actions\Action;
use Compose\Enums\FileOperation;
use Compose\Execution\ActionResult;
use Compose\RecipeContext;

class CopyFile extends Action
{
    private ?string $originalContents = null;

    private bool $targetExisted = false;

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly bool $overwrite = true,
    ) {}

    public function type(): FileOperation
    {
        return FileOperation::Copy;
    }

    public function execute(RecipeContext $context): ActionResult
    {
        $sourcePath = $this->resolvePath($this->from);
        $targetPath = $this->resolvePath($this->to);

        if (! file_exists($sourcePath)) {
            return ActionResult::failure(
                errorOutput: "Source file not found: {$this->from}",
                command: $this->descriptionArray(),
            );
        }

        if (file_exists($targetPath)) {
            $this->targetExisted = true;

            if (! $this->overwrite) {
                return ActionResult::success(
                    command: $this->descriptionArray(),
                    output: "Skipped (exists): {$this->to}",
                );
            }

            $this->originalContents = file_get_contents($targetPath) ?: null;
        }

        $directory = dirname($targetPath);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true)) {
            return ActionResult::failure(
                errorOutput: "Failed to create directory: {$directory}",
                command: $this->descriptionArray(),
            );
        }

        if (! copy($sourcePath, $targetPath)) {
            return ActionResult::failure(
                errorOutput: "Failed to copy {$this->from} to {$this->to}",
                command: $this->descriptionArray(),
            );
        }

        return ActionResult::success(
            command: $this->descriptionArray(),
            output: "Copied: {$this->from} → {$this->to}",
        );
    }

    public function describe(): string
    {
        return "copy {$this->from} → {$this->to}";
    }

    #[\Override]
    public function canRollbackDirect(): bool
    {
        return true;
    }

    public function rollbackDirect(RecipeContext $context): ActionResult
    {
        $targetPath = $this->resolvePath($this->to);

        if ($this->targetExisted && $this->originalContents !== null) {
            file_put_contents($targetPath, $this->originalContents);

            return ActionResult::success(
                command: ['rollback:copy', $this->to],
                output: "Restored: {$this->to}",
            );
        }

        if (file_exists($targetPath)) {
            unlink($targetPath);
        }

        return ActionResult::success(
            command: ['rollback:copy', $this->to],
            output: "Deleted: {$this->to}",
        );
    }

    /**
     * @return string[]
     */
    protected function descriptionArray(): array
    {
        return ['copy', $this->from, $this->to];
    }
}
