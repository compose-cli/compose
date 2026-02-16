<?php

namespace Compose\Actions\Node;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\Node;
use Compose\Enums\PackageOperation;

class NodeRun extends Action
{
    public function __construct(
        public readonly string $script,
        public readonly array|string $args = [],
        public readonly Node $manager = Node::Npm,
    ) {}

    public function type(): PackageOperation
    {
        return PackageOperation::Run;
    }

    public function command(): PendingCommand
    {
        $usesRun = match ($this->manager) {
            Node::Yarn, Node::Bun => false,
            default => true,
        };

        $cmd = $usesRun
            ? $this->node('run', $this->script)
            : $this->node($this->script);

        $args = (array) $this->args;

        if ($args !== []) {
            $usesSeparator = match ($this->manager) {
                Node::Yarn, Node::Bun => false,
                default => true,
            };

            if ($usesSeparator) {
                $cmd->argument('--', ...$args);
            } else {
                $cmd->argument(...$args);
            }
        }

        return $cmd;
    }
}
