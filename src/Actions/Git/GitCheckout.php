<?php

declare(strict_types=1);

namespace Compose\Actions\Git;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\GitOperation;

class GitCheckout extends Action
{
    public function __construct(
        public readonly string $branch,
    ) {}

    #[\Override]
    public function type(): GitOperation
    {
        return GitOperation::Checkout;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        return $this->git('checkout')->argument($this->branch);
    }

    #[\Override]
    public function rollback(): PendingCommand
    {
        return $this->git('checkout')->argument('-');
    }
}
