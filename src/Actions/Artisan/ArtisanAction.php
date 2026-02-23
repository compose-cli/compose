<?php

declare(strict_types=1);

namespace Compose\Actions;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\ArtisanOperation;

class ArtisanAction extends Action
{
    public function __construct(
        public readonly string $command,
    ) {}

    public function type(): ArtisanOperation
    {
        return ArtisanOperation::Run;
    }

    public function command(): PendingCommand
    {
        return $this->artisan(...explode(' ', $this->command));
    }

    #[\Override]
    public function preflight(): PendingCommand
    {
        return $this->artisan('--version');
    }
}