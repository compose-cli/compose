<?php

namespace Compose\Execution;

class Plan implements \Stringable
{
    public function __construct(
        public readonly string $recipeName,
        /** @var StepPlan[] */
        public readonly array $steps,
    ) {}

    public function __toString(): string
    {
        $lines = [];
        $lines[] = "  Compose — {$this->recipeName}";
        $lines[] = '  '.str_repeat('─', mb_strlen($this->recipeName) + 12);
        $lines[] = '';

        foreach ($this->steps as $i => $step) {
            $number = $i + 1;
            $lines[] = "  {$number}. {$step->name}";

            if ($step->description !== null) {
                $lines[] = "     {$step->description}";
            }

            foreach ($step->commands as $j => $command) {
                $rollback = $step->rollbackable[$j] ?? false;
                $indicator = $rollback ? '↺' : ' ';
                $lines[] = "     {$indicator} {$command}";
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
