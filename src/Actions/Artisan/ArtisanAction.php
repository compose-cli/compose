<?php

declare(strict_types=1);

namespace Compose\Actions\Artisan;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\ArtisanOperation;

class ArtisanAction extends Action
{
    public function __construct(
        public readonly string $command,
    ) {}

    #[\Override]
    public function type(): ArtisanOperation
    {
        return ArtisanOperation::Run;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        $parts = preg_split('/\s+/', trim($this->command)) ?: [];

        return $this->artisan(...$parts);
    }

    #[\Override]
    public function preflight(): PendingCommand
    {
        return $this->artisan('--version');
    }
}
