<?php

declare(strict_types=1);

namespace Compose\Actions\Quality;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\QualityOperation;

class RectorProcess extends Action
{
    #[\Override]
    public function type(): QualityOperation
    {
        return QualityOperation::Refactor;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        return new PendingCommand($this->context()->phpBinary, 'vendor/bin/rector', 'process');
    }

    #[\Override]
    public function describe(): string
    {
        return 'rector process (refactor)';
    }

    public function isInstalled(): bool
    {
        return file_exists($this->resolvePath('vendor/bin/rector'));
    }

    public function notInstalledMessage(): string
    {
        return 'Rector is not installed. Install it with: composer require rector/rector --dev';
    }
}
