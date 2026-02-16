<?php

namespace Compose\Execution;

class StepResult
{
    public function __construct(
        public readonly string $name,
        /** @var ActionResult[] */
        public readonly array $actionResults,
        public readonly bool $successful,
        public readonly bool $rolledBack = false,
        /** @var ActionResult[] */
        public readonly array $rollbackResults = [],
    ) {}

    /**
     * Create a successful step result.
     *
     * @param  ActionResult[]  $actionResults
     */
    public static function success(string $name, array $actionResults = []): static
    {
        return new static(
            name: $name,
            actionResults: $actionResults,
            successful: true,
        );
    }

    /**
     * Create a failed step result.
     *
     * @param  ActionResult[]  $actionResults
     * @param  ActionResult[]  $rollbackResults
     */
    public static function failed(
        string $name,
        array $actionResults = [],
        bool $rolledBack = false,
        array $rollbackResults = [],
    ): static {
        return new static(
            name: $name,
            actionResults: $actionResults,
            successful: false,
            rolledBack: $rolledBack,
            rollbackResults: $rollbackResults,
        );
    }
}
