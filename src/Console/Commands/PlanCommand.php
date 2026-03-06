<?php

declare(strict_types=1);

namespace Compose\Console\Commands;

use Compose\Compose;
use Compose\Events\EventDispatcher;
use Compose\Execution\Plan;
use Compose\Execution\ProcessExecutor;
use Compose\Execution\Runner;
use Compose\Execution\StepPlan;
use Compose\Step;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'plan',
    description: 'Preview what a recipe would do without executing',
)]
class PlanCommand extends Command
{
    public function __invoke(
        #[Argument(description: 'The recipe to preview')]
        string $recipe = 'recipe.php',
        #[Option(description: 'Inspect a specific step (by name or 1-based number)')]
        ?string $step = null,
        ?SymfonyStyle $io = null,
    ): int {
        $compose = Compose::fromFile($recipe);
        $plan = $this->buildPlan($compose);

        if ($step !== null) {
            return $this->showStepDetail($plan, $step, $compose->toConfig()->steps, $io);
        }

        $io?->text((string) $plan);

        return self::SUCCESS;
    }

    private function buildPlan(Compose $compose): Plan
    {
        $runner = new Runner(new ProcessExecutor, new EventDispatcher);

        return $runner->plan($compose->toConfig());
    }

    private function showStepDetail(Plan $plan, string $identifier, array $steps, ?SymfonyStyle $io): int
    {
        $index = $this->resolveStepIndex($steps, $identifier);

        if ($index === null) {
            $io?->error("Step '{$identifier}' not found.");

            if ($io !== null && $steps !== []) {
                $io->text('Available steps:');

                foreach ($steps as $i => $step) {
                    $number = $i + 1;
                    $io->text("  {$number}. {$step->name}");
                }
            }

            return self::FAILURE;
        }

        $step = $steps[$index];
        $stepPlan = $plan->steps[$index];

        $this->renderStepInspection($step, $stepPlan, $index, $io);

        return self::SUCCESS;
    }

    private function renderStepInspection(Step $step, StepPlan $stepPlan, int $index, ?SymfonyStyle $io): void
    {
        if ($io === null) {
            return;
        }

        $number = $index + 1;

        $io->section("Step {$number}: {$step->name}");

        if ($step->description !== null) {
            $io->text("  <fg=gray>{$step->description}</>");
            $io->newLine();
        }

        $io->text("  <fg=white>Failure strategy:</> {$step->failureStrategy->value}");
        $io->newLine();

        if ($stepPlan->commands === []) {
            $io->text('  <fg=yellow>No actions.</>');

            return;
        }

        $io->text('  <fg=white>Actions:</>');

        foreach ($stepPlan->commands as $j => $command) {
            $rollback = $stepPlan->rollbackable[$j] ?? false;
            $indicator = $rollback ? '<fg=green>↺</>' : ' ';
            $io->text("    {$indicator} {$command}");
        }

        $rollbackCount = count(array_filter($stepPlan->rollbackable));
        $total = count($stepPlan->commands);
        $io->newLine();
        $io->text("  {$total} action(s), {$rollbackCount} rollbackable");
    }

    /**
     * @param  Step[]  $steps
     */
    private function resolveStepIndex(array $steps, string $identifier): ?int
    {
        if (ctype_digit($identifier)) {
            $index = (int) $identifier - 1;

            if ($index >= 0 && $index < count($steps)) {
                return $index;
            }

            return null;
        }

        foreach ($steps as $i => $step) {
            if ($step->name === $identifier) {
                return $i;
            }
        }

        return null;
    }
}
