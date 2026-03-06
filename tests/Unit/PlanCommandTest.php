<?php

declare(strict_types=1);

use Compose\Console\Commands\PlanCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

describe('PlanCommand', function (): void {

    function writePlanRecipe(string $dir, string $body): string
    {
        $file = $dir.DIRECTORY_SEPARATOR.'recipe.php';
        file_put_contents($file, "<?php\nuse Compose\\Step;\n{$body}");

        return $file;
    }

    function makePlanIo(int $verbosity = OutputInterface::VERBOSITY_NORMAL): array
    {
        $output = new BufferedOutput($verbosity);
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        return [$io, $output];
    }

    it('shows plan output for a recipe', function (): void {
        $recipe = writePlanRecipe($this->tempPath, <<<'PHP'
        return compose('My App')->in('.')
            ->step('Install deps', fn(Step $step) => $step->composer(install: ['laravel/framework']), description: 'Install core packages');
        PHP);

        [$io, $output] = makePlanIo();

        $command = new PlanCommand;
        $result = $command->__invoke(recipe: $recipe, io: $io);

        $text = $output->fetch();

        expect($result)->toBe(PlanCommand::SUCCESS);
        expect($text)->toContain('My App');
        expect($text)->toContain('Install deps');
        expect($text)->toContain('Install core packages');
    });

    it('shows multiple steps in order', function (): void {
        $recipe = writePlanRecipe($this->tempPath, <<<'PHP'
        return compose('Test')->in('.')
            ->step('First Step', fn(Step $step) => $step->composer(install: ['first/pkg']))
            ->step('Second Step', fn(Step $step) => $step->composer(install: ['second/pkg']));
        PHP);

        [$io, $output] = makePlanIo();

        $command = new PlanCommand;
        $command->__invoke(recipe: $recipe, io: $io);

        $text = $output->fetch();

        expect($text)->toContain('1. First Step');
        expect($text)->toContain('2. Second Step');
    });

    it('inspects a specific step by number', function (): void {
        $recipe = writePlanRecipe($this->tempPath, <<<'PHP'
        return compose('Test')->in('.')
            ->step('First', fn(Step $step) => $step->composer(install: ['first/pkg']), description: 'First step desc')
            ->step('Second', fn(Step $step) => $step->composer(install: ['second/pkg']), description: 'Second step desc');
        PHP);

        [$io, $output] = makePlanIo();

        $command = new PlanCommand;
        $result = $command->__invoke(recipe: $recipe, step: '2', io: $io);

        $text = $output->fetch();

        expect($result)->toBe(PlanCommand::SUCCESS);
        expect($text)->toContain('Step 2: Second');
        expect($text)->toContain('Second step desc');
        expect($text)->toContain('Failure strategy:');
    });

    it('inspects a specific step by name', function (): void {
        $recipe = writePlanRecipe($this->tempPath, <<<'PHP'
        return compose('Test')->in('.')
            ->step('First', fn(Step $step) => $step->composer(install: ['first/pkg']))
            ->step('Second', fn(Step $step) => $step->composer(install: ['second/pkg']));
        PHP);

        [$io, $output] = makePlanIo();

        $command = new PlanCommand;
        $result = $command->__invoke(recipe: $recipe, step: 'Second', io: $io);

        $text = $output->fetch();

        expect($result)->toBe(PlanCommand::SUCCESS);
        expect($text)->toContain('Step 2: Second');
    });

    it('shows error for unknown step', function (): void {
        $recipe = writePlanRecipe($this->tempPath, <<<'PHP'
        return compose('Test')->in('.')
            ->step('Install', fn(Step $step) => $step->composer(install: ['pkg']));
        PHP);

        [$io, $output] = makePlanIo();

        $command = new PlanCommand;
        $result = $command->__invoke(recipe: $recipe, step: 'NonExistent', io: $io);

        $text = $output->fetch();

        expect($result)->toBe(PlanCommand::FAILURE);
        expect($text)->toContain("Step 'NonExistent' not found");
        expect($text)->toContain('Install');
    });

    it('shows action count and rollback count in step detail', function (): void {
        $recipe = writePlanRecipe($this->tempPath, <<<'PHP'
        return compose('Test')->in('.')
            ->step('Setup', fn(Step $step) => $step
                ->composer(install: ['pkg/one', 'pkg/two'])
            );
        PHP);

        [$io, $output] = makePlanIo();

        $command = new PlanCommand;
        $command->__invoke(recipe: $recipe, step: '1', io: $io);

        $text = $output->fetch();

        expect($text)->toContain('action(s)');
        expect($text)->toContain('rollbackable');
    });
});
