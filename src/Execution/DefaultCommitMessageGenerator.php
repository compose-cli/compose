<?php

namespace Compose\Execution;

use Compose\Contracts\CommitMessageGenerator;
use Compose\Step;

class DefaultCommitMessageGenerator implements CommitMessageGenerator
{
    /**
     * Generate a commit message from the step's message or name.
     *
     * @param  ActionResult[]  $actionResults
     */
    public function generate(Step $step, array $actionResults): string
    {
        return $step->message ?? "compose: {$step->name}";
    }
}
