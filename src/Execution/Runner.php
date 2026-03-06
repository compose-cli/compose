<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Actions\Action;
use Compose\Actions\Git\GitAdd;
use Compose\Actions\Git\GitCommit;
use Compose\Actions\Git\GitInit;
use Compose\Contracts\CommitMessageGenerator;
use Compose\Enums\FailureStrategy;
use Compose\Events\ActionCompleted;
use Compose\Events\ActionExecuting;
use Compose\Events\ActionFailed;
use Compose\Events\EventDispatcher;
use Compose\Events\RollbackCompleted;
use Compose\Events\RollbackStarting;
use Compose\Events\StepCompleted;
use Compose\Events\StepFailed;
use Compose\Events\StepStarting;
use Compose\Exceptions\DangerousPathException;
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
    public function run(RecipeConfig $config): RunResult
    {
        $projectContext = $config->context;
        $rollback = new RollbackManager;
        $stepResults = [];

        foreach ($config->beforeCallbacks as $callback) {
            $callback();
        }

        if ($config->fresh && $config->isNewProject) {
            $this->guardAgainstDangerousPath($projectContext->workingDirectory);
            Filesystem::deleteDirectory($projectContext->workingDirectory);
        }

        if ($config->autoCommit && ! $config->hasBase && $config->isNewProject) {
            $this->gitInit($projectContext);
        }

        foreach ($config->steps as $i => $step) {
            $this->dispatcher->dispatch(new StepStarting($step, $i));

            $context = ($config->hasBase && $i === 0) ? $config->baseContext : $projectContext;

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
                if ($step->failureStrategy === FailureStrategy::RollbackAll
                    && $rollback->hasPreviousRollbackableActions()
                ) {
                    $this->dispatcher->dispatch(new RollbackStarting($step));
                    $rollbackAllResults = $rollback->rollbackAllSteps($context, $this->executor);
                    $this->dispatcher->dispatch(new RollbackCompleted($step, $rollbackAllResults));
                }

                $this->dispatcher->dispatch(new StepFailed($step, $stepResult, $i));

                return RunResult::failed($stepResults, failedAt: $i);
            }

            $this->dispatcher->dispatch(new StepCompleted($step, $stepResult, $i));

            $isBaseCloneStep = $config->hasBase && $i === 0;

            if ($config->autoCommit && $stepResult->successful && ! $isBaseCloneStep) {
                $this->autoCommit($step, $context, $stepResult);
            }
        }

        foreach ($config->afterCallbacks as $callback) {
            $callback();
        }

        return RunResult::success($stepResults);
    }

    /**
     * Plan the recipe without executing anything.
     */
    public function plan(RecipeConfig $config): Plan
    {
        $projectContext = $config->context;
        $stepPlans = [];

        foreach ($config->steps as $i => $step) {
            $context = ($config->hasBase && $i === 0) ? $config->baseContext : $projectContext;

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
            recipeName: $config->name,
            steps: $stepPlans,
        );
    }

    /**
     * Prevent deletion of dangerous paths like cwd, home, or root.
     */
    private function guardAgainstDangerousPath(?string $path): void
    {
        if ($path === null || $path === '') {
            throw new DangerousPathException(
                'Cannot use fresh mode: no working directory specified.',
            );
        }

        $resolved = realpath($path) ?: $path;
        $cwd = realpath(getcwd()) ?: (string) getcwd();

        if ($resolved === $cwd || $resolved === '.') {
            throw new DangerousPathException(
                "Cannot use fresh mode: the path '{$path}' resolves to the current working directory.",
            );
        }

        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;

        if ($home !== null && $resolved === realpath($home)) {
            throw new DangerousPathException(
                "Cannot use fresh mode: the path '{$path}' resolves to the home directory.",
            );
        }

        $isRoot = $resolved === '/'
            || $resolved === '\\'
            || preg_match('/^[A-Z]:\\\\?$/i', $resolved);

        if ($isRoot) {
            throw new DangerousPathException(
                "Cannot use fresh mode: the path '{$path}' resolves to a filesystem root.",
            );
        }
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
        $this->executeAutoCommitAction($addAction, $context);

        $commitAction = (new GitCommit(message: $message))->withContext($context);
        $commitAction->allowFailure = true;
        $this->executeAutoCommitAction($commitAction, $context);
    }

    /**
     * Execute a single auto-commit action, firing lifecycle events.
     */
    private function executeAutoCommitAction(Action $action, RecipeContext $context): void
    {
        $this->dispatcher->dispatch(new ActionExecuting($action, autoCommit: true));

        $result = $this->executor->execute(
            $action->command()->toArray(),
            $context->workingDirectory,
        );

        if ($result->successful) {
            $this->dispatcher->dispatch(new ActionCompleted($action, $result, autoCommit: true));
        } else {
            $this->dispatcher->dispatch(new ActionFailed($action, $result, warned: true, autoCommit: true));
        }
    }
}
