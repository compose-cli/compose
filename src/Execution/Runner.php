<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Actions\Action;
use Compose\Actions\Git\GitCommit;
use Compose\Actions\Quality\PintFormat;
use Compose\Actions\Quality\RectorProcess;
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
use Symfony\Component\Process\Process;

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
                defaultTimeout: $config->timeout,
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

            if ($stepResult->successful && ! $isBaseCloneStep) {
                $this->runCodeQuality($config, $context);
            }

            if ($config->autoCommit && $stepResult->successful && ! $isBaseCloneStep) {
                $this->autoCommit($step, $context, $stepResult, $rollback);
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
        $isBaseCloneStep = fn (int $i): bool => $config->hasBase && $i === 0;

        foreach ($config->steps as $i => $step) {
            $context = $isBaseCloneStep($i) ? $config->baseContext : $projectContext;

            $step->resolveOperations();

            $commands = [];
            $rollbackable = [];

            foreach ($step->operations() as $action) {
                $action->withContext($context);
                $commands[] = $action->describe();
                $rollbackable[] = $action->canBeRolledBack();
            }

            if (! $isBaseCloneStep($i)) {
                if ($config->formatWithRector) {
                    $commands[] = (new RectorProcess)->withContext($context)->describe();
                    $rollbackable[] = false;
                }

                if ($config->formatWithPint) {
                    $commands[] = (new PintFormat)->withContext($context)->describe();
                    $rollbackable[] = false;
                }

                if ($config->autoCommit && ! $this->stepHasManualCommit($step)) {
                    $commit = (new GitCommit(
                        message: $step->message ?? "compose: {$step->name}",
                        stageAll: true,
                    ))->withContext($context);
                    $commands[] = $commit->describe();
                    $rollbackable[] = true;
                }
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

        $resolved = $this->normalizePath(realpath($path) ?: $path);
        $cwd = $this->normalizePath(realpath((string) getcwd()) ?: (string) getcwd());

        if ($resolved === $cwd || $resolved === '.') {
            throw new DangerousPathException(
                "Cannot use fresh mode: the path '{$path}' resolves to the current working directory.",
            );
        }

        if (str_starts_with($cwd, $resolved.'/')) {
            throw new DangerousPathException(
                "Cannot use fresh mode: the path '{$path}' is a parent of the current working directory.",
            );
        }

        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;

        if ($home !== null) {
            $resolvedHome = $this->normalizePath(realpath($home) ?: $home);
            if ($resolved === $resolvedHome) {
                throw new DangerousPathException(
                    "Cannot use fresh mode: the path '{$path}' resolves to the home directory.",
                );
            }
        }

        $isRoot = $resolved === '/'
            || $resolved === ''
            || preg_match('/^[A-Z]:[\\/]?$/i', $resolved);

        if ($isRoot) {
            throw new DangerousPathException(
                "Cannot use fresh mode: the path '{$path}' resolves to a filesystem root.",
            );
        }
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Initialize a git repository in the project directory.
     *
     * Uses a real process (not ProcessExecutor) so auto-commit works even
     * when command execution is faked in tests.
     */
    private function gitInit(RecipeContext $context): void
    {
        $cwd = $context->workingDirectory;

        if ($cwd !== null && $cwd !== '' && ! is_dir($cwd)) {
            mkdir($cwd, 0755, true);
        }

        $process = new Process(
            [$context->gitBinary, 'init'],
            $cwd,
        );
        $process->run();
    }

    /**
     * Auto-commit changes after a successful step.
     *
     * Skips if the step already contains a manual GitCommit action.
     * Successful commits are pushed onto the rollback stack so RollbackAll
     * can reset them atomically.
     */
    private function autoCommit(Step $step, RecipeContext $context, StepResult $stepResult, RollbackManager $rollback): void
    {
        if ($this->stepHasManualCommit($step)) {
            return;
        }

        $message = $this->commitMessageGenerator->generate($step, $stepResult->actionResults);

        $commitAction = (new GitCommit(message: $message, stageAll: true))->withContext($context);
        $commitAction->allowFailure = true;

        $result = $this->executeAutoCommitAction($commitAction, $context);

        if ($result->successful && $commitAction->canBeRolledBack()) {
            $rollback->push($commitAction);
        }
    }

    /**
     * Execute a single auto-commit action, firing lifecycle events.
     */
    private function executeAutoCommitAction(Action $action, RecipeContext $context): ActionResult
    {
        $this->dispatcher->dispatch(new ActionExecuting($action, autoCommit: true));

        $directResult = $action->execute($context);

        $result = $directResult ?? $this->executor->execute(
            $action->command()?->toArray() ?? [],
            $context->workingDirectory,
        );

        if ($result->successful) {
            $this->dispatcher->dispatch(new ActionCompleted($action, $result, autoCommit: true));
        } else {
            $this->dispatcher->dispatch(new ActionFailed($action, $result, warned: true, autoCommit: true));
        }

        return $result;
    }

    /**
     * Whether the step already queues a manual GitCommit.
     */
    private function stepHasManualCommit(Step $step): bool
    {
        foreach ($step->operations() as $operation) {
            if ($operation instanceof GitCommit) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run code quality tools (Rector, then Pint) after a successful step.
     */
    private function runCodeQuality(RecipeConfig $config, RecipeContext $context): void
    {
        if ($config->formatWithRector) {
            $this->runQualityAction(
                (new RectorProcess)->withContext($context),
                $context,
            );
        }

        if ($config->formatWithPint) {
            $this->runQualityAction(
                (new PintFormat)->withContext($context),
                $context,
            );
        }
    }

    /**
     * Execute a single code quality action, firing lifecycle events.
     *
     * If the tool is not installed in the project, a warning is dispatched
     * without attempting to run the command.
     */
    private function runQualityAction(PintFormat|RectorProcess $action, RecipeContext $context): void
    {
        $action->allowFailure = true;

        if (! $action->isInstalled()) {
            $result = ActionResult::failure(
                errorOutput: $action->notInstalledMessage(),
                command: [],
            );

            $this->dispatcher->dispatch(new ActionFailed($action, $result, warned: true, codeQuality: true));

            return;
        }

        $this->dispatcher->dispatch(new ActionExecuting($action, codeQuality: true));

        $command = $action->command();

        $result = $this->executor->execute(
            $command->toArray(),
            $context->workingDirectory,
            $command->getTimeout(),
        );

        if ($result->successful) {
            $this->dispatcher->dispatch(new ActionCompleted($action, $result, codeQuality: true));
        } else {
            $this->dispatcher->dispatch(new ActionFailed($action, $result, warned: true, codeQuality: true));
        }
    }
}
