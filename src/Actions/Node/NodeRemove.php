<?php

namespace Compose\Actions\Node;

use Compose\Enums\PackageOperation;

class NodeRemove extends NodeAction
{
    public function type(): PackageOperation
    {
        return ($this->dev ? PackageOperation::RemoveDev : PackageOperation::Remove);
    }

    public function getCommand(): string
    {
        $command = $this->dev ? $this->removeDevCommand : $this->removeCommand;

        return $this->getBinary() . ' ' . sprintf($command, $this->getEscapedPackages());
    }

    public function getRollbackCommand(): string
    {
        $command = $this->dev ? $this->installDevCommand : $this->installCommand;

        return $this->getBinary() . ' ' . sprintf($command, $this->getEscapedPackages());
    }
}
