<?php

namespace Compose\Actions\Composer;

use Compose\Actions\Action;

abstract class ComposerAction extends Action
{
    protected string $installCommand = 'install %s';
    protected string $installDevCommand = 'install %s --dev';
    protected string $removeCommand = 'remove %s';
    protected string $removeDevCommand = 'remove %s --dev';

    public function __construct(
        public readonly array|string $packages,
        public readonly bool $dev = false,
        protected readonly string|null $bin = null,
    ) {}

    abstract public function getCommand(): string;

    protected function getEscapedPackages(): string
    {
        if (is_string($this->packages)) {
            return escapeshellarg($this->packages);
        }

        return implode(' ', array_map(fn($package) => escapeshellarg($package), $this->packages));
    }
}