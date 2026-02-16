<?php

namespace Compose\Actions\Composer;

use Compose\Actions\PendingCommand;
use Compose\Enums\PackageOperation;

class ComposerInstall extends ComposerAction
{
    public function type(): PackageOperation
    {
        return $this->dev ? PackageOperation::InstallDev : PackageOperation::Install;
    }

    public function command(): PendingCommand
    {
        return $this->composer('require')
            ->when($this->dev, fn (PendingCommand $cmd) => $cmd->flag('--dev'))
            ->argument(...$this->packageList());
    }

    public function rollback(): PendingCommand
    {
        return $this->composer('remove')
            ->when($this->dev, fn (PendingCommand $cmd) => $cmd->flag('--dev'))
            ->argument(...$this->packageList());
    }

    #[\Override]
    public function canBeRolledBack(): bool
    {
        return true;
    }
}
