<?php

namespace Compose\Actions;

use Compose\Contracts\Operation;
use Compose\RecipeContext;
use RuntimeException;

abstract class Action
{
    protected ?RecipeContext $context = null;

    /**
     * The operation type this action represents.
     */
    abstract public function type(): Operation;

    /**
     * Build the command to execute.
     */
    abstract public function command(): PendingCommand;

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
     * Whether this action can be rolled back.
     */
    public function canBeRolledBack(): bool
    {
        return $this->rollback() !== null;
    }

    /**
     * A human-readable description of what this action does.
     */
    public function describe(): string
    {
        return $this->command()->toString();
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
     * Get the recipe context, or throw if not set.
     */
    protected function context(): RecipeContext
    {
        return $this->context ?? throw new RuntimeException(
            'Action context has not been set. The runner must call withContext() before execution.',
        );
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
        return new PendingCommand($this->context()->nodeManager->value, ...$subcommand);
    }

    /**
     * Create a pending command for the git binary.
     */
    protected function git(string ...$subcommand): PendingCommand
    {
        return new PendingCommand($this->context()->gitBinary, ...$subcommand);
    }
}
