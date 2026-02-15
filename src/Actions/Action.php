<?php

namespace Compose\Actions;

use Compose\Contracts\Operation;

abstract class Action
{
    abstract public function getCommand(): string;

    abstract public function type(): Operation;
}