<?php

namespace Compose\Execution\Pipes;

use Closure;
use Compose\Execution\StepContext;

class ResolveOperations
{
    /**
     * Resolve the step's operations by calling its callback.
     */
    public function handle(StepContext $context, Closure $next): mixed
    {
        $context->step->resolveOperations();

        return $next($context);
    }
}
