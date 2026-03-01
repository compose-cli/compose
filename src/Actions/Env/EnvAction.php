<?php

declare(strict_types=1);

namespace Compose\Actions\Env;

use Closure;
use Compose\Actions\Action;
use Compose\Builders\EnvBuilder;
use Compose\Enums\EnvOperation;
use Compose\Execution\ActionResult;
use Compose\RecipeContext;
use Compose\Support\TextFile\EnvFileParser;

class EnvAction extends Action
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
    public function type(): EnvOperation
    {
        return EnvOperation::Env;
    }

    #[\Override]
    public function execute(RecipeContext $context): ActionResult
    {
        $fullPath = $this->resolvePath($this->path);

        if (file_exists($fullPath)) {
            $this->fileExisted = true;
            $this->originalContents = file_get_contents($fullPath) ?: '';
        } else {
            $this->originalContents = null;
        }

        $contents = $this->originalContents ?? '';
        $parser = EnvFileParser::parse($contents);

        $this->applyOperations($parser, $this->operations);

        $newContents = $parser->toString();

        $directory = dirname($fullPath);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true)) {
            return ActionResult::failure(
                errorOutput: "Failed to create directory: {$directory}",
                command: $this->descriptionArray(),
            );
        }

        if (file_put_contents($fullPath, $newContents) === false) {
            return ActionResult::failure(
                errorOutput: "Failed to write env file: {$this->path}",
                command: $this->descriptionArray(),
            );
        }

        $opCount = $this->countOperations($this->operations);

        return ActionResult::success(
            command: $this->descriptionArray(),
            output: "Updated {$this->path} ({$opCount} operation".($opCount !== 1 ? 's' : '').')',
        );
    }

    #[\Override]
    public function describe(): string
    {
        $opCount = $this->countOperations($this->operations);

        return "env {$this->path} ({$opCount} operation".($opCount !== 1 ? 's' : '').')';
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
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return ActionResult::success(
                command: ['rollback:env', $this->path],
                output: "Deleted: {$this->path}",
            );
        }

        if ($this->originalContents !== null) {
            file_put_contents($fullPath, $this->originalContents);
        }

        return ActionResult::success(
            command: ['rollback:env', $this->path],
            output: "Restored: {$this->path}",
        );
    }

    /**
     * @param  list<array{type: string, ...}>  $operations
     */
    private function applyOperations(EnvFileParser $parser, array $operations): void
    {
        foreach ($operations as $op) {
            match ($op['type']) {
                'set' => $parser->set($op['key'], $op['value'], $op['after'] ?? null),
                'remove' => $parser->remove($op['key']),
                'comment' => $parser->comment($op['key']),
                'uncomment' => $parser->uncomment($op['key']),
                'section' => $parser->addSection($op['header'], $op['values'], $op['after'] ?? null),
                'when' => $this->applyConditional($parser, $op['condition'], $op['callback']),
                default => null,
            };
        }
    }

    private function applyConditional(EnvFileParser $parser, Closure $condition, Closure $callback): void
    {
        if (! $condition($parser)) {
            return;
        }

        $builder = new EnvBuilder;
        $callback($builder);
        $this->applyOperations($parser, $builder->operations());
    }

    /**
     * Count non-conditional operations (for describe output).
     *
     * @param  list<array{type: string, ...}>  $operations
     */
    private function countOperations(array $operations): int
    {
        $count = 0;

        foreach ($operations as $op) {
            $count += $op['type'] === 'when' ? 1 : 1;
        }

        return $count;
    }

    /**
     * @return string[]
     */
    private function descriptionArray(): array
    {
        return ['env', $this->path];
    }
}
