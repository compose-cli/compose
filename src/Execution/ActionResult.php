<?php

namespace Compose\Execution;

use Compose\Actions\Action;

class ActionResult
{
    public function __construct(
        public readonly array $command,
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $errorOutput,
        public readonly bool $successful,
        public readonly ?float $duration = null,
        public readonly ?Action $action = null,
        public readonly bool $warned = false,
    ) {}

    /**
     * Create a successful result (useful for testing and fakes).
     */
    public static function success(
        array $command = [],
        string $output = '',
        ?Action $action = null,
    ): static {
        return new static(
            command: $command,
            exitCode: 0,
            output: $output,
            errorOutput: '',
            successful: true,
            action: $action,
        );
    }

    /**
     * Create a failed result (useful for testing and fakes).
     */
    public static function failure(
        int $exitCode = 1,
        string $errorOutput = '',
        array $command = [],
        ?Action $action = null,
    ): static {
        return new static(
            command: $command,
            exitCode: $exitCode,
            output: '',
            errorOutput: $errorOutput,
            successful: false,
            action: $action,
        );
    }
}
