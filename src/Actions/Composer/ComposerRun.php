<?php

declare(strict_types=1);

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

    #[\Override]
    public function type(): PackageOperation
    {
        return PackageOperation::Run;
    }

    #[\Override]
    public function defaultTimeout(): float
    {
        return 120.0;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        return $this->composer('run', $this->script)
            ->withArgs((array) $this->args);
    }
}
