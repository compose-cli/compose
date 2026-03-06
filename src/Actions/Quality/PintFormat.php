<?php

declare(strict_types=1);

namespace Compose\Actions\Quality;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\QualityOperation;

class PintFormat extends Action
{
    #[\Override]
    public function type(): QualityOperation
    {
        return QualityOperation::Format;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        return new PendingCommand($this->context()->phpBinary, 'vendor/bin/pint');
    }

    #[\Override]
    public function describe(): string
    {
        return 'pint (format)';
    }

    public function isInstalled(): bool
    {
        return file_exists($this->resolvePath('vendor/bin/pint'));
    }

    public function notInstalledMessage(): string
    {
        return 'Laravel Pint is not installed. Install it with: composer require laravel/pint --dev';
    }
}
