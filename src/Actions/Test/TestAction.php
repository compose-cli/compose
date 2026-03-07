<?php

declare(strict_types=1);

namespace Compose\Actions\Test;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\VerifyOperation;

class TestAction extends Action
{
    public function __construct(
        public readonly string $path,
        bool $allowFailure = false,
    ) {
        $this->allowFailure = $allowFailure;
    }

    #[\Override]
    public function type(): VerifyOperation
    {
        return VerifyOperation::Test;
    }

    #[\Override]
    public function defaultTimeout(): float
    {
        return 300.0;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        return $this->artisan('test', '--filter='.$this->path);
    }

    #[\Override]
    public function describe(): string
    {
        return "test: {$this->path}";
    }

    #[\Override]
    public function preflight(): PendingCommand
    {
        return $this->artisan('--version')->timeout(10.0);
    }
}
