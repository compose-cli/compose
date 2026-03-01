<?php

declare(strict_types=1);

namespace Compose\Events;

use Compose\Actions\Action;

class ActionExecuting
{
    public function __construct(
        public readonly Action $action,
    ) {}
}
