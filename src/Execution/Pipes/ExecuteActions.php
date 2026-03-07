<?php

declare(strict_types=1);

namespace Compose\Execution\Pipes;

use Closure;
use Compose\Actions\Action;
use Compose\Events\ActionCompleted;
use Compose\Events\ActionExecuting;
use Compose\Events\ActionFailed;
use Compose\Events\RollbackCompleted;
use Compose\Events\RollbackStarting;
use Compose\Execution\ActionResult;
use Compose\Execution\StepContext;
use Compose\Execution\StepResult;

class ExecuteActions
{
    /**
     * @var array<class-string, true>
     */
    private array $preflighted = [];

    /**
     * Execute each action in the step, managing rollback on failure.
     */
    public function handle(StepContext $context, Closure $next): mixed
    {
        $actionResults = [];

        foreach ($context->step->operations() as $action) {
            $action->withContext($context->recipeContext);

            if ($preflightFailure = $this->runPreflight($action, $context)) {
                $actionResults[] = $preflightFailure;

                $context->result = StepResult::failed(
                    name: $context->step->name,
                    actionResults: $actionResults,
                );

                return $context;
            }

            $context->dispatcher->dispatch(new ActionExecuting($action));

            $result = $this->executeAction($action, $context);

            if (! $result->successful && $context->step->shouldWarnOnFailure($action)) {
                $actionResults[] = new ActionResult(
                    command: $result->command,
                    exitCode: $result->exitCode,
                    output: $result->output,
                    errorOutput: $result->errorOutput,
                    successful: false,
                    duration: $result->duration,
                    action: $action,
                    warned: true,
                );

                $context->dispatcher->dispatch(new ActionFailed($action, $result, warned: true));

                continue;
            }

            $actionResults[] = new ActionResult(
                command: $result->command,
                exitCode: $result->exitCode,
                output: $result->output,
                errorOutput: $result->errorOutput,
                successful: $result->successful,
                duration: $result->duration,
                action: $action,
            );

            if (! $result->successful) {
                $context->dispatcher->dispatch(new ActionFailed($action, $result));

                $rollbackResults = [];

                if ($context->rollback->hasRollbackableActions()) {
                    $context->dispatcher->dispatch(new RollbackStarting($context->step));
                    $rollbackResults = $context->rollback->rollbackCurrentStep(
                        $context->recipeContext,
                        $context->executor,
                    );
                    $context->dispatcher->dispatch(new RollbackCompleted($context->step, $rollbackResults));
                }

                $context->result = StepResult::failed(
                    name: $context->step->name,
                    actionResults: $actionResults,
                    rolledBack: $rollbackResults !== [],
                    rollbackResults: $rollbackResults,
                );

                return $context;
            }

            $context->rollback->push($action);
            $context->dispatcher->dispatch(new ActionCompleted($action, $result));
        }

        $context->result = StepResult::success(
            name: $context->step->name,
            actionResults: $actionResults,
        );

        return $next($context);
    }

    /**
     * Execute an action directly or via command.
     */
    protected function executeAction(Action $action, StepContext $context): ActionResult
    {
        $startTime = microtime(true);

        // Try direct execution first (file operations, HTTP fetches, etc.)
        $directResult = $action->execute($context->recipeContext);

        if ($directResult !== null) {
            return new ActionResult(
                command: $directResult->command,
                exitCode: $directResult->exitCode,
                output: $directResult->output,
                errorOutput: $directResult->errorOutput,
                successful: $directResult->successful,
                duration: microtime(true) - $startTime,
                action: $action,
            );
        }

        // Fall through to command-based execution
        $command = $action->command();

        if ($command === null) {
            return ActionResult::failure(
                errorOutput: 'Action has neither execute() nor command() implementation:'.$action::class,
                command: [],
            );
        }

        $timeout = $command->getTimeout()
            ?? $context->step->timeout
            ?? $context->defaultTimeout
            ?? $action->defaultTimeout();

        return $context->executor->execute(
            $command->toArray(),
            $context->recipeContext->workingDirectory,
            $timeout,
        );
    }

    /**
     * Run the preflight check for an action if it hasn't been run yet.
     *
     * Returns an ActionResult on failure, or null if the check passed or wasn't needed.
     */
    private function runPreflight(\Compose\Actions\Action $action, StepContext $context): ?ActionResult
    {
        $class = $action::class;

        if (isset($this->preflighted[$class])) {
            return null;
        }

        $preflight = $action->preflight();

        if ($preflight === null) {
            $this->preflighted[$class] = true;

            return null;
        }

        $result = $context->executor->execute(
            $preflight->toArray(),
            $context->recipeContext->workingDirectory,
            $preflight->getTimeout(),
        );

        $this->preflighted[$class] = true;

        if (! $result->successful) {
            return new ActionResult(
                command: $result->command,
                exitCode: $result->exitCode,
                output: $result->output,
                errorOutput: "Preflight check failed: {$preflight->toString()}",
                successful: false,
                duration: $result->duration,
                action: $action,
            );
        }

        return null;
    }
}
