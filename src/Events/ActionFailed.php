<?php

declare(strict_types=1);

namespace Compose\Events;

use Compose\Actions\Action;
use Compose\Execution\ActionResult;

class ActionFailed
{
    public function __construct(
        public readonly Action $action,
        public readonly ActionResult $result,
        public readonly bool $warned = false,
        public readonly bool $autoCommit = false,
        public readonly bool $codeQuality = false,
    ) {}
}
