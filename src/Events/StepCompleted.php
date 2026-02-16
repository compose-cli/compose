<?php

namespace Compose\Events;

use Compose\Execution\StepResult;
use Compose\Step;

class StepCompleted
{
    public function __construct(
        public readonly Step $step,
        public readonly StepResult $result,
        public readonly int $index,
    ) {}
}
