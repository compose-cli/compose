<?php

namespace Compose\Actions\Git;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\GitOperation;

class GitInit extends Action
{
    public function type(): GitOperation
    {
        return GitOperation::Init;
    }

    public function command(): PendingCommand
    {
        return $this->git('init');
    }
}
