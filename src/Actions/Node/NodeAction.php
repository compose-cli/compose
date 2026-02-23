<?php

namespace Compose\Actions\Node;

use Compose\Actions\Action;
use Compose\Enums\Node;

abstract class NodeAction extends Action
{
    public function __construct(
        public readonly array|string $packages,
        public readonly bool $dev = false,
    ) {}

    /**
     * @return string[]
     */
    protected function packageList(): array
    {
        return (array) $this->packages;
    }

    protected function installVerb(): string
    {
        return match ($this->manager()) {
            Node::Npm => 'install',
            default => 'add',
        };
    }

    protected function removeVerb(): string
    {
        return match ($this->manager()) {
            Node::Npm => 'uninstall',
            default => 'remove',
        };
    }

    protected function devFlag(): string
    {
        return match ($this->manager()) {
            Node::Npm, Node::Pnpm => '--save-dev',
            default => '--dev',
        };
    }
}
