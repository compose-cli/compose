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
    public function defaultTimeout(): float
    {
        $cmd = explode(' ', trim($this->command))[0] ?? '';

        return match (true) {
            str_starts_with($cmd, 'make:') => 15.0,
            str_starts_with($cmd, 'migrate'),
            str_starts_with($cmd, 'db:seed') => 120.0,
            $cmd === 'vendor:publish' => 30.0,
            default => 60.0,
        };
    }

    #[\Override]
    public function preflight(): PendingCommand
    {
        return $this->artisan('--version')->timeout(10.0);
    }
}
