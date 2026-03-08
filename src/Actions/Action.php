<?php

declare(strict_types=1);

namespace Compose\Actions;

use Compose\Contracts\Operation;
use Compose\Enums\Node;
use Compose\Execution\ActionResult;
use Compose\Execution\ProcessExecutor;
use Compose\RecipeContext;
use RuntimeException;

abstract class Action
{
    protected ?RecipeContext $context = null;

    protected ?ProcessExecutor $processExecutor = null;

    public bool $allowFailure = false;

    /**
     * The operation type this action represents.
     */
    abstract public function type(): Operation;

    /**
     * Build the command to execute.
     */
    public function command(): ?PendingCommand
    {
        return null;
    }

    /**
     * Execute this action directly (not via shell).
     *
     * Override this for actions that perform PHP-native operations
     * (file I/O, HTTP requests, etc.) instead of shelling out.
     *
     * Return null to fall through to command-based execution.
     */
    public function execute(RecipeContext $context): ?ActionResult
    {
        return null;
    }

    /**
     * Whether this action uses direct execution rather than a shell command.
     */
    public function isDirect(): bool
    {
        return $this->command() === null;
    }

    /**
     * Build the command to roll back this action.
     *
     * Return null if this action cannot be rolled back.
     */
    public function rollback(): ?PendingCommand
    {
        return null;
    }

    /**
     * Roll back this action directly (not via shell).
     *
     * Override this for direct actions that need PHP-native rollback.
     * Return null to fall through to command-based rollback.
     */
    public function rollbackDirect(RecipeContext $context): ?ActionResult
    {
        return null;
    }

    /**
     * Whether this action can be rolled back.
     */
    public function canBeRolledBack(): bool
    {
        return $this->rollback() !== null || $this->canRollbackDirect();
    }

    /**
     * Whether this action supports direct rollback.
     */
    public function canRollbackDirect(): bool
    {
        return false;
    }

    /**
     * The default timeout in seconds for this action type.
     *
     * Override in subclasses to provide sensible per-action defaults.
     * Return null to defer to the step, compose, or executor default.
     */
    public function defaultTimeout(): ?float
    {
        return null;
    }

    /**
     * A command that must succeed before any action of this type executes.
     *
     * Return null if no preflight check is needed.
     */
    public function preflight(): ?PendingCommand
    {
        return null;
    }

    /**
     * A human-readable description of what this action does.
     */
    public function describe(): string
    {
        $command = $this->command();

        return $command !== null ? $command->toString() : static::class;
    }

    /**
     * Set the recipe context on this action.
     */
    public function withContext(RecipeContext $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Set the process executor on this action.
     */
    public function withExecutor(ProcessExecutor $executor): static
    {
        $this->processExecutor = $executor;

        return $this;
    }

    /**
     * Get the process executor, or throw if not set.
     */
    protected function executor(): ProcessExecutor
    {
        return $this->processExecutor ?? throw new RuntimeException(
            'Action requires a ProcessExecutor. The runner must call withExecutor() before execution.',
        );
    }

    /**
     * Get the recipe context, or throw if not set.
     */
    protected function context(): RecipeContext
    {
        return $this->context ?? throw new RuntimeException(
            'Action context has not been set. The runner must call withContext() before execution.',
        );
    }

    /**
     * Get the configured node package manager.
     */
    protected function manager(): Node
    {
        return $this->context()->nodeManager;
    }

    /**
     * Create a pending command for the composer binary.
     */
    protected function composer(string ...$subcommand): PendingCommand
    {
        return new PendingCommand($this->context()->composerBinary, ...$subcommand);
    }

    /**
     * Create a pending command for the node package manager binary.
     */
    protected function node(string ...$subcommand): PendingCommand
    {
        return new PendingCommand($this->manager()->value, ...$subcommand);
    }

    /**
     * Create a pending command for the git binary.
     */
    protected function git(string ...$subcommand): PendingCommand
    {
        return new PendingCommand($this->context()->gitBinary, ...$subcommand);
    }

    /**
     * Create a pending command for php artisan.
     */
    protected function artisan(string ...$subcommand): PendingCommand
    {
        return new PendingCommand($this->context()->phpBinary, 'artisan', ...$subcommand);
    }

    protected function resolvePath(string $path): string
    {
        $cwd = $this->context()->workingDirectory;

        if ($cwd === null) {
            return $path;
        }

        // Already absolute
        if (str_starts_with($path, '/') || preg_match('/^[A-Z]:\\\\/i', $path)) {
            return $path;
        }

        return rtrim($cwd, '/\\').DIRECTORY_SEPARATOR.$path;
    }
}
