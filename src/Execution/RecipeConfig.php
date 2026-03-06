<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\RecipeContext;
use Compose\Step;

final class RecipeConfig
{
    public function __construct(
        public readonly string $name,
        public readonly RecipeContext $context,
        public readonly ?RecipeContext $baseContext,
        public readonly bool $fresh,
        public readonly bool $autoCommit,
        public readonly bool $smartCommit,
        /** @var Step[] */
        public readonly array $steps,
        /** @var callable[] */
        public readonly array $beforeCallbacks,
        /** @var callable[] */
        public readonly array $afterCallbacks,
    ) {}

    public bool $hasBase {
        get => $this->baseContext !== null;
    }

    /**
     * Create a modified copy of this config with selective overrides.
     */
    public function withOverrides(?bool $autoCommit = null, ?array $steps = null): static
    {
        return new self(
            name: $this->name,
            context: $this->context,
            baseContext: $this->baseContext,
            fresh: $this->fresh,
            autoCommit: $autoCommit ?? $this->autoCommit,
            smartCommit: $this->smartCommit,
            steps: $steps ?? $this->steps,
            beforeCallbacks: $this->beforeCallbacks,
            afterCallbacks: $this->afterCallbacks,
        );
    }
}
