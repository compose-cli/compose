<?php

namespace Compose\Events;

use Compose\Execution\ActionResult;
use Compose\Step;

class RollbackCompleted
{
    public function __construct(
        public readonly Step $step,
        /** @var ActionResult[] */
        public readonly array $results,
    ) {}
}
