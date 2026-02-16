<?php

namespace Compose\Execution;

class RunResult
{
    public function __construct(
        /** @var StepResult[] */
        public readonly array $stepResults,
        public readonly bool $successful,
        public readonly ?int $failedAtStep = null,
    ) {}

    /**
     * The number of steps that completed successfully.
     */
    public int $stepsCompleted {
        get => count(array_filter($this->stepResults, fn (StepResult $r) => $r->successful));
    }

    /**
     * The total number of steps that were attempted.
     */
    public int $stepsTotal {
        get => count($this->stepResults);
    }

    /**
     * Create a successful run result.
     *
     * @param  StepResult[]  $stepResults
     */
    public static function success(array $stepResults): static
    {
        return new static(
            stepResults: $stepResults,
            successful: true,
        );
    }

    /**
     * Create a failed run result.
     *
     * @param  StepResult[]  $stepResults
     */
    public static function failed(array $stepResults, int $failedAt): static
    {
        return new static(
            stepResults: $stepResults,
            successful: false,
            failedAtStep: $failedAt,
        );
    }
}
