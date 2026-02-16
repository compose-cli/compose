<?php

namespace Compose;

use Compose\Enums\Node;

class RecipeContext
{
    public function __construct(
        public readonly string $composerBinary = 'composer',
        public readonly string $gitBinary = 'git',
        public readonly Node $nodeManager = Node::Npm,
        public readonly ?string $workingDirectory = null,
    ) {}

    /**
     * Create a new context with a different working directory.
     */
    public function withWorkingDirectory(?string $directory): static
    {
        return new static(
            composerBinary: $this->composerBinary,
            gitBinary: $this->gitBinary,
            nodeManager: $this->nodeManager,
            workingDirectory: $directory,
        );
    }
}
