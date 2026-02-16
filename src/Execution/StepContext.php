<?php

namespace Compose\Execution;

use Compose\Events\EventDispatcher;
use Compose\RecipeContext;
use Compose\Step;

class StepContext
{
    public ?StepResult $result = null;

    public function __construct(
        public readonly Step $step,
        public readonly RecipeContext $recipeContext,
        public readonly ProcessExecutor $executor,
        public readonly RollbackManager $rollback,
        public readonly EventDispatcher $dispatcher,
    ) {}
}
