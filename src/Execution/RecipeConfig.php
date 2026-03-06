<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Enums\TaskType;
use Compose\RecipeContext;
use Compose\Step;

final class RecipeConfig
{
    public function __construct(
        public readonly string $name,
        public readonly TaskType $taskType,
        public readonly RecipeContext $context,
        public readonly ?RecipeContext $baseContext,
        public readonly bool $fresh,
        public readonly bool $autoCommit,
        public readonly bool $smartCommit,
        public readonly bool $formatWithPint,
        public readonly bool $formatWithRector,
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

    public bool $isNewProject {
        get => $this->taskType === TaskType::NewProject;
    }

    /**
     * Create a modified copy of this config with selective overrides.
     */
    public function withOverrides(
        ?bool $autoCommit = null,
        ?bool $formatWithPint = null,
        ?bool $formatWithRector = null,
        ?array $steps = null,
    ): static {
        return new self(
            name: $this->name,
            taskType: $this->taskType,
            context: $this->context,
            baseContext: $this->baseContext,
            fresh: $this->fresh,
            autoCommit: $autoCommit ?? $this->autoCommit,
            smartCommit: $this->smartCommit,
            formatWithPint: $formatWithPint ?? $this->formatWithPint,
            formatWithRector: $formatWithRector ?? $this->formatWithRector,
            steps: $steps ?? $this->steps,
            beforeCallbacks: $this->beforeCallbacks,
            afterCallbacks: $this->afterCallbacks,
        );
    }
}
