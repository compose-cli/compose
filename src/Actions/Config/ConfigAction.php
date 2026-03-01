<?php

declare(strict_types=1);

namespace Compose\Actions\Config;

use Compose\Actions\Action;
use Compose\Enums\ConfigOperation;
use Compose\Execution\ActionResult;
use Compose\RecipeContext;
use Compose\Support\PhpFile\ConfigFileEditor;
use RuntimeException;
use Throwable;

class ConfigAction extends Action
{
    private ?string $originalContents = null;

    private bool $fileExisted = false;

    /**
     * @param  list<array{type: string, ...}>  $operations
     */
    public function __construct(
        public readonly string $path,
        public readonly array $operations,
    ) {}

    #[\Override]
    public function type(): ConfigOperation
    {
        return ConfigOperation::Config;
    }

    #[\Override]
    public function execute(RecipeContext $context): ActionResult
    {
        $fullPath = $this->resolvePath($this->path);

        if (! file_exists($fullPath)) {
            return ActionResult::failure(
                errorOutput: "Config file not found: {$this->path}",
                command: $this->descriptionArray(),
            );
        }

        $this->fileExisted = true;
        $this->originalContents = file_get_contents($fullPath) ?: '';

        try {
            $editor = ConfigFileEditor::fromCode($this->originalContents);

            $this->applyOperations($editor);

            $newContents = $editor->render();

            if (file_put_contents($fullPath, $newContents) === false) {
                return ActionResult::failure(
                    errorOutput: "Failed to write config file: {$this->path}",
                    command: $this->descriptionArray(),
                );
            }

            $opCount = count($this->operations);

            return ActionResult::success(
                command: $this->descriptionArray(),
                output: "Modified {$this->path} ({$opCount} operation".($opCount !== 1 ? 's' : '').')',
            );
        } catch (Throwable $e) {
            return ActionResult::failure(
                errorOutput: "Failed to modify config {$this->path}: {$e->getMessage()}",
                command: $this->descriptionArray(),
            );
        }
    }

    #[\Override]
    public function describe(): string
    {
        $opCount = count($this->operations);

        return "config {$this->path} ({$opCount} operation".($opCount !== 1 ? 's' : '').')';
    }

    #[\Override]
    public function canRollbackDirect(): bool
    {
        return true;
    }

    #[\Override]
    public function rollbackDirect(RecipeContext $context): ActionResult
    {
        $fullPath = $this->resolvePath($this->path);

        if (! $this->fileExisted) {
            return ActionResult::success(
                command: ['rollback:config', $this->path],
                output: "Nothing to restore: {$this->path}",
            );
        }

        if ($this->originalContents !== null) {
            file_put_contents($fullPath, $this->originalContents);
        }

        return ActionResult::success(
            command: ['rollback:config', $this->path],
            output: "Restored: {$this->path}",
        );
    }

    private function applyOperations(ConfigFileEditor $editor): void
    {
        foreach ($this->operations as $op) {
            match ($op['type']) {
                'set' => $editor->set($op['key'], $op['value']),
                'remove' => $editor->remove($op['key']),
                'merge' => $editor->merge($op['key'], $op['value']),
                'push' => $editor->push($op['key'], $op['value']),
                'comment' => $editor->comment($op['key']),
                default => throw new RuntimeException("Unknown config operation: {$op['type']}"),
            };
        }
    }

    /**
     * @return string[]
     */
    private function descriptionArray(): array
    {
        return ['config', $this->path];
    }
}
