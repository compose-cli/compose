<?php

namespace Compose\Actions\Composer;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\PackageOperation;

class ComposerRun extends Action
{
    public function __construct(
        public readonly string $script,
        public readonly array|string $args = [],
    ) {}

    public function type(): PackageOperation
    {
        return PackageOperation::Run;
    }

    public function command(): PendingCommand
    {
        $cmd = $this->composer('run', $this->script);

        $args = (array) $this->args;

        if ($args !== []) {
            $cmd->argument('--', ...$args);
        }

        return $cmd;
    }
}
