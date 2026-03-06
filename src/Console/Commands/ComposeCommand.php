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
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'compose',
    description: 'Compose your recipe',
    help: 'This command will compose your recipe',
)]
class ComposeCommand extends Command
{
    public function __invoke(
        #[Argument(description: 'The recipe to compose')]
        string $recipe = 'recipe.php',
        #[Option(description: 'Should we dry run the composition?')]
        bool $dry = false,
        ?SymfonyStyle $io = null,
    ): int {
        $compose = require $recipe;

        if (! $compose instanceof Compose) {
            throw new \RuntimeException('The recipe must return a Compose object.');
        }

        if ($dry) {
            return $this->showPlan($compose, $io);
        }

        return $this->executeRecipe($compose, $io);
    }

    private function showPlan(Compose $compose, ?SymfonyStyle $io): int
    {
        $plan = $compose->plan();

        if ($io !== null) {
            $io->text((string) $plan);
        }

        return self::SUCCESS;
    }

    private function executeRecipe(Compose $compose, ?SymfonyStyle $io): int
    {
        $dispatcher = new EventDispatcher;

        $this->registerEventListeners($dispatcher, $io);

        $result = $compose->run($dispatcher);

        if ($result->hasWarnings && $io !== null) {
            $warningCount = count($result->warnings);
            $io->warning("{$warningCount} action(s) failed but were allowed to continue.");
        }

        if ($result->successful) {
            $io?->success("All {$result->stepsCompleted} steps completed successfully.");

            return self::SUCCESS;
        }

        $io?->error("Failed at step {$result->failedAtStep}. {$result->stepsCompleted}/{$result->stepsTotal} steps completed.");

        return self::FAILURE;
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
                $duration = ' <fg=gray>('.number_format($event->result->duration, 2).'s)</>';
            }

            $io->text("  <fg=green>✓</> {$event->action->describe()}{$duration}");

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
                $duration = ' <fg=gray>('.number_format($event->result->duration, 2).'s)</>';
            }

            if ($event->warned) {
                $io->text("  <fg=yellow>⚠</> {$event->action->describe()}{$duration} <fg=yellow>(warning, continuing)</>");

                if ($event->result->errorOutput !== '') {
                    $io->text("    <fg=yellow>{$event->result->errorOutput}</>");
                }

                return;
            }

            $io->text("  <fg=red>✗</> {$event->action->describe()}{$duration}");

            if ($event->result->errorOutput !== '') {
                $io->text("    <fg=red>{$event->result->errorOutput}</>");
            }
        });

        $dispatcher->listen(StepCompleted::class, function (StepCompleted $event) use ($io, &$stepTimers): void {
            $duration = '';

            if ($io->isVerbose() && isset($stepTimers[$event->index])) {
                $elapsed = microtime(true) - $stepTimers[$event->index];
                $duration = ' <fg=gray>('.number_format($elapsed, 2).'s)</>';
                unset($stepTimers[$event->index]);
            }

            $io->text("<fg=green>  ✓ {$event->step->name}{$duration}</>");
        });

        $dispatcher->listen(StepFailed::class, function (StepFailed $event) use ($io, &$stepTimers): void {
            $duration = '';

            if ($io->isVerbose() && isset($stepTimers[$event->index])) {
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
