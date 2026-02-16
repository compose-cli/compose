<?php

namespace Compose\Contracts;

use Compose\Execution\ActionResult;
use Compose\Step;

interface CommitMessageGenerator
{
    /**
     * Generate a commit message for the given step.
     *
     * @param  ActionResult[]  $actionResults
     */
    public function generate(Step $step, array $actionResults): string;
}
