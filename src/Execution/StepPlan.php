<?php

namespace Compose\Execution;

class StepPlan
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        /** @var string[] Human-readable command descriptions */
        public readonly array $commands,
        /** @var bool[] Whether each command is rollbackable */
        public readonly array $rollbackable,
    ) {}
}
