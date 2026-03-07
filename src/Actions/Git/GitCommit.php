<?php

declare(strict_types=1);

namespace Compose\Actions\Git;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\GitOperation;

class GitCommit extends Action
{
    public function __construct(
        public readonly ?string $message = null,
    ) {}

    #[\Override]
    public function type(): GitOperation
    {
        return GitOperation::Commit;
    }

    #[\Override]
    public function defaultTimeout(): float
    {
        return 30.0;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        return $this->git('commit')
            ->flag('-m')
            ->argument($this->message ?? '');
    }
}
