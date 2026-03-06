<?php

declare(strict_types=1);

namespace Compose\Console\Commands;

use Compose\Compose;
use Compose\Events\ActionCompleted;
use Compose\Events\ActionExecuting;
use Compose\Events\ActionFailed;
use Compose\Events\EventDispatcher;
use Compose\Events\RollbackCompleted;
use Compose\Events\RollbackStarting;
use Compose\Events\StepCompleted;
use Compose\Events\StepFailed;
use Compose\Events\StepStarting;
use Compose\Execution\ProcessExecutor;
use Compose\Execution\RecipeConfig;
use Compose\Execution\Runner;
use Compose\Step;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'run',
    description: 'Execute a recipe',
    help: 'This command will execute your recipe',
)]
class RunCommand extends Command
{
    public function __invoke(
        #[Argument(description: 'The recipe to compose')]
        string $recipe = 'recipe.php',
        #[Option(description: 'Run only a specific step (by name or 1-based number)')]
        ?string $step = null,
        #[Option(description: 'Resume from a specific step (by name or 1-based number)')]
        ?string $from = null,
        #[Option(name: 'no-commit', description: 'Skip all git commits')]
        bool $noCommit = false,
        #[Option(name: 'no-format', description: 'Skip code quality formatting (Pint/Rector)')]
        bool $noFormat = false,
        ?SymfonyStyle $io = null,
    ): int {
        if ($step !== null && $from !== null) {
            $io?->error('The --step and --from options are mutually exclusive.');

            return self::FAILURE;
        }

        $compose = Compose::fromFile($recipe);
        $config = $compose->toConfig();

        if ($step !== null) {
            $config = $this->filterToStep($config, $step, $io);

            if ($config === null) {
                return self::FAILURE;
            }
        }

        if ($from !== null) {
            $config = $this->filterFromStep($config, $from, $io);

            if ($config === null) {
                return self::FAILURE;
            }
        }

        if ($noCommit) {
            $config = $config->withOverrides(autoCommit: false);
        }

        if ($noFormat) {
            $config = $config->withOverrides(formatWithPint: false, formatWithRector: false);
        }

        return $this->executeRecipe($config, $io);
    }

    private function executeRecipe(RecipeConfig $config, ?SymfonyStyle $io): int
    {
        $dispatcher = new EventDispatcher;

        $this->registerEventListeners($dispatcher, $io);

        $startTime = microtime(true);

        $runner = new Runner(new ProcessExecutor, $dispatcher);
        $result = $runner->run($config);

        $elapsed = number_format(microtime(true) - $startTime, 2);

        if ($io !== null) {
            $io->newLine();

            if ($result->hasWarnings) {
                $warningCount = count($result->warnings);
                $io->text("  <fg=yellow>⚠ {$warningCount} action(s) failed but were allowed to continue.</>");
            }

            if ($result->successful) {
                $io->text("  <fg=green>✓ All {$result->stepsCompleted} steps completed successfully in {$elapsed}s</>");
            } else {
                $io->text("  <fg=red>✗ Failed at step {$result->failedAtStep}. {$result->stepsCompleted}/{$result->stepsTotal} steps completed ({$elapsed}s elapsed)</>");
            }

            $io->newLine();
        }

        return $result->successful ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Resolve a step identifier (name or 1-based number) to an array index.
     *
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

    private function filterToStep(RecipeConfig $config, string $identifier, ?SymfonyStyle $io): ?RecipeConfig
    {
        $index = $this->resolveStepIndex($config->steps, $identifier);

        if ($index === null) {
            $this->showStepNotFound($identifier, $config->steps, $io);

            return null;
        }

        return $config->withOverrides(steps: [$config->steps[$index]]);
    }

    private function filterFromStep(RecipeConfig $config, string $identifier, ?SymfonyStyle $io): ?RecipeConfig
    {
        $index = $this->resolveStepIndex($config->steps, $identifier);

        if ($index === null) {
            $this->showStepNotFound($identifier, $config->steps, $io);

            return null;
        }

        return $config->withOverrides(steps: array_slice($config->steps, $index));
    }

    /**
     * @param  Step[]  $steps
     */
    private function showStepNotFound(string $identifier, array $steps, ?SymfonyStyle $io): void
    {
        $io?->error("Step '{$identifier}' not found.");

        if ($io !== null && $steps !== []) {
            $io->text('Available steps:');

            foreach ($steps as $i => $step) {
                $number = $i + 1;
                $io->text("  {$number}. {$step->name}");
            }
        }
    }

    private function registerEventListeners(EventDispatcher $dispatcher, ?SymfonyStyle $io): void
    {
        if ($io === null) {
            return;
        }

        $stepTimers = [];

        $dispatcher->listen(StepStarting::class, function (StepStarting $event) use ($io, &$stepTimers): void {
            $stepTimers[$event->index] = microtime(true);
            $io->section($event->step->name);

            if ($event->step->message !== null) {
                $io->text($event->step->message);
            }
        });

        $dispatcher->listen(ActionExecuting::class, function (ActionExecuting $event) use ($io): void {
            if ($event->autoCommit || $event->codeQuality) {
                $io->text("  <fg=gray>▸ {$event->action->describe()}</>");

                return;
            }

            $io->text("  <fg=gray>▸ {$event->action->describe()}</>");

            if ($io->isVerbose()) {
                $command = $event->action->command();

                if ($command !== null && $command->toString() !== $event->action->describe()) {
                    $io->text("    <fg=gray>$ {$command->toString()}</>");
                }
            }
        });

        $dispatcher->listen(ActionCompleted::class, function (ActionCompleted $event) use ($io): void {
            $duration = '';

            if ($io->isVerbose() && $event->result->duration !== null) {
                $duration = ' ('.number_format($event->result->duration, 2).'s)';
            }

            if ($event->autoCommit || $event->codeQuality) {
                $io->text("  <fg=gray>✓ {$event->action->describe()}{$duration}</>");

                return;
            }

            $io->text("  <fg=green>✓</> {$event->action->describe()}<fg=gray>{$duration}</>");

            if ($io->isVerbose() && $event->result->output !== '') {
                foreach (explode("\n", trim($event->result->output)) as $line) {
                    $io->text("    <fg=gray>{$line}</>");
                }
            }

            if ($io->isVeryVerbose() && $event->result->errorOutput !== '') {
                foreach (explode("\n", trim($event->result->errorOutput)) as $line) {
                    $io->text("    <fg=yellow>{$line}</>");
                }
            }

            if ($io->isDebug()) {
                $io->text("    <fg=gray>exit code: {$event->result->exitCode}</>");
            }
        });

        $dispatcher->listen(ActionFailed::class, function (ActionFailed $event) use ($io): void {
            $duration = '';

            if ($io->isVerbose() && $event->result->duration !== null) {
                $duration = ' ('.number_format($event->result->duration, 2).'s)';
            }

            if ($event->autoCommit) {
                $io->text("  <fg=gray>✗ {$event->action->describe()}{$duration} (skipped)</>");

                return;
            }

            if ($event->codeQuality) {
                $io->text("  <fg=yellow>⚠</> {$event->action->describe()}<fg=gray>{$duration}</> <fg=yellow>(warning)</>");

                if ($event->result->errorOutput !== '') {
                    $io->text("    <fg=yellow>{$event->result->errorOutput}</>");
                }

                return;
            }

            if ($event->warned) {
                $io->text("  <fg=yellow>⚠</> {$event->action->describe()}<fg=gray>{$duration}</> <fg=yellow>(warning, continuing)</>");

                if ($event->result->errorOutput !== '') {
                    $io->text("    <fg=yellow>{$event->result->errorOutput}</>");
                }

                return;
            }

            $io->text("  <fg=red>✗</> {$event->action->describe()}<fg=gray>{$duration}</>");

            if ($event->result->errorOutput !== '') {
                $io->text("    <fg=red>{$event->result->errorOutput}</>");
            }
        });

        $dispatcher->listen(StepCompleted::class, function (StepCompleted $event) use ($io, &$stepTimers): void {
            $duration = '';

            if (isset($stepTimers[$event->index])) {
                $elapsed = microtime(true) - $stepTimers[$event->index];
                $duration = ' <fg=gray>('.number_format($elapsed, 2).'s)</>';
                unset($stepTimers[$event->index]);
            }

            $io->text("<fg=green>  ✓ {$event->step->name}{$duration}</>");
        });

        $dispatcher->listen(StepFailed::class, function (StepFailed $event) use ($io, &$stepTimers): void {
            $duration = '';

            if (isset($stepTimers[$event->index])) {
                $elapsed = microtime(true) - $stepTimers[$event->index];
                $duration = ' <fg=gray>('.number_format($elapsed, 2).'s)</>';
                unset($stepTimers[$event->index]);
            }

            $io->text("<fg=red>  ✗ {$event->step->name}{$duration}</>");
        });

        $dispatcher->listen(RollbackStarting::class, function (RollbackStarting $event) use ($io): void {
            $io->text('  <fg=yellow>↺ Rolling back...</>');
        });

        $dispatcher->listen(RollbackCompleted::class, function (RollbackCompleted $event) use ($io): void {
            $count = count($event->results);
            $io->text("  <fg=yellow>↺ Rolled back {$count} action(s)</>");
        });
    }
}
