<?php

namespace Compose\Events;

use Compose\Actions\Action;
use Compose\Execution\ActionResult;

class ActionCompleted
{
    public function __construct(
        public readonly Action $action,
        public readonly ActionResult $result,
    ) {}
}
