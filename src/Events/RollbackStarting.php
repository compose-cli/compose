<?php

namespace Compose\Events;

use Compose\Step;

class RollbackStarting
{
    public function __construct(
        public readonly Step $step,
    ) {}
}
