<?php

declare(strict_types=1);

namespace Compose\Actions\Git;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\GitOperation;

class GitInit extends Action
{
    #[\Override]
    public function type(): GitOperation
    {
        return GitOperation::Init;
    }

    #[\Override]
    public function defaultTimeout(): float
    {
        return 15.0;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        return $this->git('init');
    }
}
