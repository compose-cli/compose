<?php

namespace Compose\Actions\Node;

use Compose\Actions\Action;
use Compose\Enums\Node;

abstract class NodeAction extends Action
{
    protected string $installCommand;
    protected string $installDevCommand;
    protected string $removeCommand;
    protected string $removeDevCommand;

    public function __construct(
        public readonly array|string $packages,
        public readonly bool $dev = false,
        protected readonly Node|string $manager = Node::Npm,
    ) {
        [$this->installCommand, $this->installDevCommand, $this->removeCommand, $this->removeDevCommand] = match ($this->manager) {
            Node::Npm => ['install %s', 'install %s --save-dev', 'uninstall %s', 'uninstall %s --save-dev'],
            Node::Yarn => ['add %s', 'add %s --dev', 'remove %s', 'remove %s --dev'],
            Node::Pnpm => ['add %s', 'add %s --save-dev', 'remove %s', 'remove %s --save-dev'],
            Node::Bun => ['add %s', 'add %s --dev', 'remove %s', 'remove %s --dev'],
            default => ['add %s', 'add %s --dev', 'remove %s', 'remove %s --dev'],
        };
    }

    abstract public function getCommand(): string;

    protected function getBinary(): string
    {
        return $this->manager instanceof Node ? $this->manager->value : $this->manager;
    }

    protected function getEscapedPackages(): string
    {
        if (is_string($this->packages)) {
            return escapeshellarg($this->packages);
        }

        return implode(' ', array_map(fn($package) => escapeshellarg($package), $this->packages));
    }
}
