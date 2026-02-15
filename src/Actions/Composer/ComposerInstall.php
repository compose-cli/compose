<?php

namespace Compose\Actions\Composer;

use Compose\Actions\Action;
use Compose\Enums\PackageOperation;

class ComposerInstall implements Action
{
    private string $installCommand = 'install';

    private string $installDevCommand = 'install-dev';

    public function __construct(
        public readonly array $packages,
        public readonly bool $dev = false,
        public readonly string|null $bin = null,
    ) {}

    public function type(): PackageOperation
    {
        return ($this->dev ? PackageOperation::InstallDev : PackageOperation::Install);
    }

    public function getCommand(): string
    {
        return $this->bin . ' ' . ($this->dev ? $this->installDevCommand : $this->installCommand) . ' ' . $this->getEscapedPackages();
    }

    private function getEscapedPackages(): string
    {
        return implode(' ', array_map(fn($package) => escapeshellarg($package), $this->packages));
    }
}