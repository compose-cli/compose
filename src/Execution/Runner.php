<?php

namespace Compose\Execution;

use Compose\Compose;
use Compose\Events\EventDispatcher;
use Compose\Events\StepCompleted;
use Compose\Events\StepFailed;
use Compose\Events\StepStarting;
use Compose\Execution\Pipes\ExecuteActions;
use Compose\Execution\Pipes\ResolveOperations;

class Runner
{
    public function __construct(
        protected ProcessExecutor $executor,
        protected EventDispatcher $dispatcher,
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
}
