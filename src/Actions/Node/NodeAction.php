<?php

namespace Compose\Actions\Node;

use Compose\Actions\Action;
use Compose\Enums\Node;

abstract class NodeAction extends Action
{
    public function __construct(
        public readonly array|string $packages,
        public readonly bool $dev = false,
        public readonly Node $manager = Node::Npm,
    ) {}

    /**
     * Get the packages as a flat array.
     *
     * @return string[]
     */
    protected function packageList(): array
    {
        return (array) $this->packages;
    }

    /**
     * Get the install subcommand for this manager.
     */
    protected function installVerb(): string
    {
        return match ($this->manager) {
            Node::Npm => 'install',
            default => 'add',
        };
    }

    /**
     * Get the remove subcommand for this manager.
     */
    protected function removeVerb(): string
    {
        return match ($this->manager) {
            Node::Npm => 'uninstall',
            default => 'remove',
        };
    }

    /**
     * Get the dev flag for this manager.
     */
    protected function devFlag(): string
    {
        return match ($this->manager) {
            Node::Npm, Node::Pnpm => '--save-dev',
            default => '--dev',
        };
    }
}
