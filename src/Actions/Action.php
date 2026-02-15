<?php

namespace Compose\Actions;

use Compose\Contracts\Operation;

abstract class Action
{
    abstract public function type(): Operation;
    abstract public function getCommand(): string;
    
    public function getRollbackCommand(): string|null
    {
        return null;
    }
    
    public function canBeRolledBack(): bool
    {
        return $this->getRollbackCommand() !== null;
    }
}