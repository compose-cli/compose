<?php

namespace Compose\Actions\Node;

use Compose\Actions\PendingCommand;
use Compose\Enums\PackageOperation;

class NodeRemove extends NodeAction
{
    public function type(): PackageOperation
    {
        return $this->dev ? PackageOperation::RemoveDev : PackageOperation::Remove;
    }

    public function command(): PendingCommand
    {
        return $this->node($this->removeVerb())
            ->when($this->dev, fn (PendingCommand $cmd) => $cmd->flag($this->devFlag()))
            ->argument(...$this->packageList());
    }

    public function rollback(): PendingCommand
    {
        return $this->node($this->installVerb())
            ->when($this->dev, fn (PendingCommand $cmd) => $cmd->flag($this->devFlag()))
            ->argument(...$this->packageList());
    }

    #[\Override]
    public function canBeRolledBack(): bool
    {
        return true;
    }
}
