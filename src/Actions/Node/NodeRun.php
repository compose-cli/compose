<?php

namespace Compose\Actions\Node;

use Compose\Actions\Action;
use Compose\Enums\Node;
use Compose\Enums\PackageOperation;

class NodeRun extends Action
{
    public function __construct(
        public readonly string $script,
        public readonly array|string $args = [],
        protected readonly Node|string $manager = Node::Npm,
    ) {}

    public function type(): PackageOperation
    {
        return PackageOperation::Run;
    }

    public function getCommand(): string
    {
        $binary = $this->getBinary();
        $script = escapeshellarg($this->script);
        $escapedArgs = $this->getEscapedArgs();

        $usesRun = match ($this->manager) {
            Node::Yarn, Node::Bun => false,
            default => true,
        };

        $usesSeparator = match ($this->manager) {
            Node::Yarn, Node::Bun => false,
            default => true,
        };

        $command = $usesRun
            ? $binary . ' run ' . $script
            : $binary . ' ' . $script;

        if ($escapedArgs !== '') {
            $command .= $usesSeparator ? ' -- ' . $escapedArgs : ' ' . $escapedArgs;
        }

        return $command;
    }

    protected function getBinary(): string
    {
        return $this->manager instanceof Node ? $this->manager->value : $this->manager;
    }

    protected function getEscapedArgs(): string
    {
        if (is_string($this->args)) {
            return escapeshellarg($this->args);
        }

        if (empty($this->args)) {
            return '';
        }

        return implode(' ', array_map(fn($arg) => escapeshellarg($arg), $this->args));
    }
}
