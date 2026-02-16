<?php

namespace Compose\Execution;

use Compose\Actions\Action;
use Compose\RecipeContext;

class RollbackManager
{
    /**
     * @var array<string, Action[]>
     */
    protected array $stacks = [];

    /**
     * The working directory used for each step.
     *
     * @var array<string, string|null>
     */
    protected array $workingDirectories = [];

    protected ?string $currentStep = null;

    /**
     * Begin tracking actions for a new step.
     */
    public function beginStep(string $stepName, ?string $workingDirectory = null): void
    {
        $this->currentStep = $stepName;
        $this->stacks[$stepName] = [];
        $this->workingDirectories[$stepName] = $workingDirectory;
    }

    /**
     * Record a successfully executed action onto the current step's stack.
     */
    public function push(Action $action): void
    {
        if ($this->currentStep === null) {
            return;
        }

        $this->stacks[$this->currentStep][] = $action;
    }

    /**
     * Roll back all actions in the current step (LIFO order).
     *
     * Only actions that can be rolled back will be executed.
     * Returns the results of all rollback attempts.
     *
     * @return ActionResult[]
     */
    public function rollbackCurrentStep(
        RecipeContext $context,
        ProcessExecutor $executor,
    ): array {
        if ($this->currentStep === null) {
            return [];
        }

        $cwd = $this->workingDirectories[$this->currentStep] ?? $context->workingDirectory;
        $actions = array_reverse($this->stacks[$this->currentStep] ?? []);
        $results = [];

        foreach ($actions as $action) {
            $rollbackCommand = $action->rollback();

            if ($rollbackCommand === null) {
                continue;
            }

            $results[] = $executor->execute(
                $rollbackCommand->toArray(),
                $cwd,
            );
        }

        $this->stacks[$this->currentStep] = [];

        return $results;
    }

    /**
     * Roll back all completed steps in reverse order (LIFO).
     *
     * Skips the current step (which should already be handled by rollbackCurrentStep).
     * Uses the working directory that was active when each step was executed.
     *
     * @return ActionResult[]
     */
    public function rollbackAllSteps(ProcessExecutor $executor): array
    {
        $stepNames = array_keys($this->stacks);
        $results = [];

        foreach (array_reverse($stepNames) as $stepName) {
            if ($stepName === $this->currentStep) {
                continue;
            }

            $cwd = $this->workingDirectories[$stepName] ?? null;
            $actions = array_reverse($this->stacks[$stepName] ?? []);

            foreach ($actions as $action) {
                $rollbackCommand = $action->rollback();

                if ($rollbackCommand === null) {
                    continue;
                }

                $results[] = $executor->execute(
                    $rollbackCommand->toArray(),
                    $cwd,
                );
            }

            $this->stacks[$stepName] = [];
        }

        return $results;
    }

    /**
     * Check if the current step has any actions that can be rolled back.
     */
    public function hasRollbackableActions(): bool
    {
        if ($this->currentStep === null) {
            return false;
        }

        foreach ($this->stacks[$this->currentStep] ?? [] as $action) {
            if ($action->canBeRolledBack()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if any completed (non-current) steps have rollbackable actions.
     */
    public function hasPreviousRollbackableActions(): bool
    {
        foreach ($this->stacks as $stepName => $actions) {
            if ($stepName === $this->currentStep) {
                continue;
            }

            foreach ($actions as $action) {
                if ($action->canBeRolledBack()) {
                    return true;
                }
            }
        }

        return false;
    }
}
