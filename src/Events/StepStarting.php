<?php

namespace Compose\Events;

use Compose\Step;

class StepStarting
{
    public function __construct(
        public readonly Step $step,
        public readonly int $index,
    ) {}
}
