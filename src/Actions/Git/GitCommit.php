<?php

namespace Compose\Actions\Git;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\GitOperation;

class GitCommit extends Action
{
    public function __construct(
        public readonly ?string $message = null,
    ) {}

    public function type(): GitOperation
    {
        return GitOperation::Commit;
    }

    public function command(): PendingCommand
    {
        return $this->git('commit')
            ->flag('-m')
            ->argument($this->message ?? '');
    }
}
