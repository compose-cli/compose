<?php

namespace Compose\Execution;

use Compose\Actions\Git\GitAdd;
use Compose\Actions\Git\GitCommit;
use Compose\Actions\Git\GitInit;
use Compose\Compose;
use Compose\Contracts\CommitMessageGenerator;
use Compose\Events\EventDispatcher;
use Compose\Events\StepCompleted;
use Compose\Events\StepFailed;
use Compose\Events\StepStarting;
use Compose\Execution\Pipes\ExecuteActions;
use Compose\Execution\Pipes\ResolveOperations;
use Compose\Filesystem;
use Compose\RecipeContext;
use Compose\Step;

class Runner
{
    public function __construct(
        protected ProcessExecutor $executor,
        protected EventDispatcher $dispatcher,
        protected CommitMessageGenerator $commitMessageGenerator = new DefaultCommitMessageGenerator,
    ) {}

    /**
     * Execute the full recipe and return the result.
     */
    public function run(Compose $recipe): RunResult
    {
        $hasBase = $recipe->getBaseRepo() !== null;
        $baseContext = $hasBase ? $recipe->getBaseContext() : null;
        $projectContext = $recipe->getContext();
        $steps = $recipe->getSteps();
        $rollback = new RollbackManager;
        $stepResults = [];

        foreach ($recipe->getBeforeCallbacks() as $callback) {
            $callback($recipe);
        }

        if ($recipe->isFresh()) {
            Filesystem::deleteDirectory($projectContext->workingDirectory);
        }

        if ($recipe->shouldAutoCommit() && ! $hasBase) {
            $this->gitInit($projectContext);
        }

        foreach ($steps as $i => $step) {
            $this->dispatcher->dispatch(new StepStarting($step, $i));

            $context = ($hasBase && $i === 0) ? $baseContext : $projectContext;

            $rollback->beginStep($step->name, $context->workingDirectory);

            $stepContext = new StepContext(
                step: $step,
                recipeContext: $context,
                executor: $this->executor,
                rollback: $rollback,
                dispatcher: $this->dispatcher,
            );

            $result = (new Pipeline)
                ->send($stepContext)
                ->through([
                    ResolveOperations::class,
                    ExecuteActions::class,
                ])
                ->thenReturn();

            $stepResult = $result->result ?? StepResult::success($step->name);
            $stepResults[] = $stepResult;

            if (! $stepResult->successful) {
                if ($rollback->hasPreviousRollbackableActions()) {
                    $rollback->rollbackAllSteps($this->executor);
                }

                $this->dispatcher->dispatch(new StepFailed($step, $stepResult, $i));

                return RunResult::failed($stepResults, failedAt: $i);
            }

            $this->dispatcher->dispatch(new StepCompleted($step, $stepResult, $i));

            $isBaseCloneStep = $hasBase && $i === 0;

            if ($recipe->shouldAutoCommit() && $stepResult->successful && ! $isBaseCloneStep) {
                $this->autoCommit($step, $context, $stepResult);
            }
        }

        foreach ($recipe->getAfterCallbacks() as $callback) {
            $callback($recipe);
        }

        return RunResult::success($stepResults);
    }

    /**
     * Plan the recipe without executing anything.
     */
    public function plan(Compose $recipe): Plan
    {
        $hasBase = $recipe->getBaseRepo() !== null;
        $baseContext = $hasBase ? $recipe->getBaseContext() : null;
        $projectContext = $recipe->getContext();
        $stepPlans = [];

        foreach ($recipe->getSteps() as $i => $step) {
            $context = ($hasBase && $i === 0) ? $baseContext : $projectContext;

            $step->resolveOperations();

            $commands = [];
            $rollbackable = [];

            foreach ($step->operations() as $action) {
                $action->withContext($context);
                $commands[] = $action->describe();
                $rollbackable[] = $action->canBeRolledBack();
            }

            $stepPlans[] = new StepPlan(
                name: $step->name,
                description: $step->description,
                commands: $commands,
                rollbackable: $rollbackable,
            );
        }

        return new Plan(
            recipeName: $recipe->getName(),
            steps: $stepPlans,
        );
    }

    /**
     * Initialize a git repository in the project directory.
     */
    private function gitInit(RecipeContext $context): void
    {
        $action = (new GitInit)->withContext($context);
        $action->allowFailure = true;

        $this->executor->execute(
            $action->command()->toArray(),
            $context->workingDirectory,
        );
    }

    /**
     * Auto-commit changes after a successful step.
     *
     * Skips if the step already contains a manual GitCommit action.
     */
    private function autoCommit(Step $step, RecipeContext $context, StepResult $stepResult): void
    {
        foreach ($step->operations() as $operation) {
            if ($operation instanceof GitCommit) {
                return;
            }
        }

        $message = $this->commitMessageGenerator->generate($step, $stepResult->actionResults);

        $addAction = (new GitAdd)->withContext($context);
        $addAction->allowFailure = true;

        $this->executor->execute(
            $addAction->command()->toArray(),
            $context->workingDirectory,
        );

        $commitAction = (new GitCommit(message: $message))->withContext($context);
        $commitAction->allowFailure = true;

        $this->executor->execute(
            $commitAction->command()->toArray(),
            $context->workingDirectory,
        );
    }
}
