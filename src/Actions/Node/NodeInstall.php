<?php

declare(strict_types=1);

namespace Compose\Actions\Node;

use Compose\Actions\PendingCommand;
use Compose\Enums\PackageOperation;

class NodeInstall extends NodeAction
{
    #[\Override]
    public function type(): PackageOperation
    {
        return $this->dev ? PackageOperation::InstallDev : PackageOperation::Install;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        return $this->node($this->installVerb())
            ->when($this->dev, fn (PendingCommand $cmd) => $cmd->flag($this->devFlag()))
            ->argument(...$this->packageList());
    }

    #[\Override]
    public function rollback(): PendingCommand
    {
        return $this->node($this->removeVerb())
            ->when($this->dev, fn (PendingCommand $cmd) => $cmd->flag($this->devFlag()))
            ->argument(...$this->packageList());
    }

    #[\Override]
    public function canBeRolledBack(): bool
    {
        return true;
    }
}
