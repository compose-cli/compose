<?php

declare(strict_types=1);

namespace Compose\Actions\File;

use Compose\Actions\Action;
use Compose\Enums\FileOperation;
use Compose\Execution\ActionResult;
use Compose\RecipeContext;

class AppendFile extends Action
{
    private ?int $appendedBytes = null;

    public function __construct(
        public readonly string $path,
        public readonly string $contents,
    ) {}

    #[\Override]
    public function type(): FileOperation
    {
        return FileOperation::Append;
    }

    #[\Override]
    public function execute(RecipeContext $context): ActionResult
    {
        $fullPath = $this->resolvePath($this->path);

        if (! file_exists($fullPath)) {
            return ActionResult::failure(
                errorOutput: "File not found: {$this->path}",
                command: $this->descriptionArray(),
            );
        }

        $result = file_put_contents($fullPath, $this->contents, FILE_APPEND);

        if ($result === false) {
            return ActionResult::failure(
                errorOutput: "Failed to append to file: {$this->path}",
                command: $this->descriptionArray(),
            );
        }

        $this->appendedBytes = $result;

        return ActionResult::success(
            command: $this->descriptionArray(),
            output: "Appended {$result} bytes to {$this->path}",
        );
    }

    #[\Override]
    public function describe(): string
    {
        $size = strlen($this->contents);

        return "append to {$this->path} ({$size} bytes)";
    }

    #[\Override]
    public function canRollbackDirect(): bool
    {
        return true;
    }

    #[\Override]
    public function rollbackDirect(RecipeContext $context): ActionResult
    {
        if ($this->appendedBytes === null || $this->appendedBytes === 0) {
            return ActionResult::success(
                command: ['rollback:append', $this->path],
                output: "Nothing to rollback for {$this->path}",
            );
        }

        $fullPath = $this->resolvePath($this->path);

        if (! file_exists($fullPath)) {
            return ActionResult::success(
                command: ['rollback:append', $this->path],
                output: "File already removed: {$this->path}",
            );
        }

        $currentSize = filesize($fullPath);

        if ($currentSize === false || $currentSize < $this->appendedBytes) {
            return ActionResult::failure(
                errorOutput: "Cannot truncate {$this->path}: file size changed unexpectedly",
                command: ['rollback:append', $this->path],
            );
        }

        $handle = fopen($fullPath, 'r+');

        if ($handle === false) {
            return ActionResult::failure(
                errorOutput: "Failed to open {$this->path} for rollback",
                command: ['rollback:append', $this->path],
            );
        }

        ftruncate($handle, $currentSize - $this->appendedBytes);
        fclose($handle);

        return ActionResult::success(
            command: ['rollback:append', $this->path],
            output: "Truncated {$this->appendedBytes} bytes from {$this->path}",
        );
    }

    /**
     * @return string[]
     */
    protected function descriptionArray(): array
    {
        return ['append', $this->path];
    }
}
