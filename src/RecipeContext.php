<?php

declare(strict_types=1);

namespace Compose;

use Compose\Enums\Node;

class RecipeContext
{
    public function __construct(
        public readonly string $phpBinary = 'php',
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
            phpBinary: $this->phpBinary,
            composerBinary: $this->composerBinary,
            gitBinary: $this->gitBinary,
            nodeManager: $this->nodeManager,
            workingDirectory: $directory,
        );
    }
}
