<?php

namespace Compose\Events;

use Compose\Execution\StepResult;
use Compose\Step;

class StepFailed
{
    public function __construct(
        public readonly Step $step,
        public readonly StepResult $result,
        public readonly int $index,
    ) {}
}
