<?php

namespace Compose\Actions\File;

use Compose\Actions\Action;
use Compose\Enums\FileOperation;
use Compose\Execution\ActionResult;
use Compose\RecipeContext;

class ReadFile extends Action
{
    public function __construct(
        public readonly string $path,
    ) {}

    public function type(): FileOperation
    {
        return FileOperation::Read;
    }

    public function execute(RecipeContext $context): ActionResult
    {
        $fullPath = $this->resolvePath($this->path);

        if (! file_exists($fullPath)) {
            return ActionResult::failure(
                errorOutput: "File not found: {$this->path}",
                command: $this->descriptionArray(),
            );
        }

        if (! is_readable($fullPath)) {
            return ActionResult::failure(
                errorOutput: "File not readable: {$this->path}",
                command: $this->descriptionArray(),
            );
        }

        $contents = file_get_contents($fullPath);

        if ($contents === false) {
            return ActionResult::failure(
                errorOutput: "Failed to read file: {$this->path}",
                command: $this->descriptionArray(),
            );
        }

        return ActionResult::success(
            command: $this->descriptionArray(),
            output: $contents,
        );
    }

    public function describe(): string
    {
        return "read {$this->path}";
    }

    /**
     * @return string[]
     */
    protected function descriptionArray(): array
    {
        return ['read', $this->path];
    }
}
