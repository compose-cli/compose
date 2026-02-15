<?php

namespace Compose\Actions\Composer;

use Compose\Actions\Action;
use Compose\Enums\PackageOperation;

class ComposerRemove implements Action
{
    private string $removeCommand = 'remove';

    private string $removeDevCommand = 'remove-dev';

    public function __construct(
        public readonly array $packages,
        public readonly bool $dev = false,
        public readonly string|null $bin = null,
    ) {}

    public function type(): PackageOperation
    {
        return ($this->dev ? PackageOperation::RemoveDev : PackageOperation::Remove);
    }

    public function getCommand(): string
    {
        return $this->bin . ' ' . ($this->dev ? $this->removeDevCommand : $this->removeCommand) . ' ' . $this->getEscapedPackages();
    }

    private function getEscapedPackages(): string
    {
        return implode(' ', array_map(fn($package) => escapeshellarg($package), $this->packages));
    }
}