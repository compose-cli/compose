<?php

declare(strict_types=1);

namespace Compose\Actions\Git;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\GitOperation;

class GitAdd extends Action
{
    #[\Override]
    public function type(): GitOperation
    {
        return GitOperation::Add;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        return $this->git('add', '-A');
    }
}
