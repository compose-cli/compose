<?php

declare(strict_types=1);

namespace Compose\Actions\Composer;

use Compose\Actions\PendingCommand;
use Compose\Enums\PackageOperation;

class ComposerRemove extends ComposerAction
{
    #[\Override]
    public function type(): PackageOperation
    {
        return $this->dev ? PackageOperation::RemoveDev : PackageOperation::Remove;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        return $this->composer('remove')
            ->when($this->dev, fn (PendingCommand $cmd) => $cmd->flag('--dev'))
            ->argument(...$this->packageList());
    }

    #[\Override]
    public function rollback(): PendingCommand
    {
        return $this->composer('require')
            ->when($this->dev, fn (PendingCommand $cmd) => $cmd->flag('--dev'))
            ->argument(...$this->packageList());
    }

    #[\Override]
    public function canBeRolledBack(): bool
    {
        return true;
    }
}
