<?php

declare(strict_types=1);

use Compose\Console\Commands\ComposeCommand;
use Compose\Execution\ActionResult;
use Compose\Execution\ProcessExecutor;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

describe('ComposeCommand verbose output', function (): void {

    afterEach(function (): void {
        ProcessExecutor::reset();
    });

    function writeRecipe(string $dir, string $body): string
    {
        $file = $dir.DIRECTORY_SEPARATOR.'recipe.php';
        file_put_contents($file, "<?php\nuse Compose\\Step;\n{$body}");

        return $file;
    }

    function makeIo(int $verbosity): array
    {
        $output = new BufferedOutput($verbosity);
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        return [$io, $output];
    }

    it('does not show action output at normal verbosity', function (): void {
        ProcessExecutor::fake([
            'composer require laravel/framework' => ActionResult::success(
                command: ['composer', 'require', 'laravel/framework'],
                output: 'Package installed successfully.',
            ),
        ]);

        $recipe = writeRecipe($this->tempPath, <<<'PHP'
        return compose('Test')->in('.')->step('Install', fn(Step $step) => $step->composer(install: ['laravel/framework']));
        PHP);

        [$io, $output] = makeIo(OutputInterface::VERBOSITY_NORMAL);

        $command = new ComposeCommand;
        $command->__invoke(recipe: $recipe, io: $io);

        $text = $output->fetch();

        expect($text)->not->toContain('Package installed successfully.');
        expect($text)->not->toContain('exit code:');
    });

    it('shows action stdout and duration at verbose level', function (): void {
        ProcessExecutor::fake([
            'composer require laravel/framework' => ActionResult::success(
                command: ['composer', 'require', 'laravel/framework'],
                output: "Using version ^10.0\nPackage installed.",
            ),
        ]);

        $recipe = writeRecipe($this->tempPath, <<<'PHP'
        return compose('Test')->in('.')->step('Install', fn(Step $step) => $step->composer(install: ['laravel/framework']));
        PHP);

        [$io, $output] = makeIo(OutputInterface::VERBOSITY_VERBOSE);

        $command = new ComposeCommand;
        $command->__invoke(recipe: $recipe, io: $io);

        $text = $output->fetch();

        expect($text)->toContain('Using version ^10.0');
        expect($text)->toContain('Package installed.');
        expect($text)->toMatch('/\d+\.\d+s/');
    });

    it('does not show stderr for successful actions at verbose level', function (): void {
        ProcessExecutor::fake([
            'composer require pkg' => new ActionResult(
                command: ['composer', 'require', 'pkg'],
                exitCode: 0,
                output: 'installed',
                errorOutput: 'some deprecation warning',
                successful: true,
            ),
        ]);

        $recipe = writeRecipe($this->tempPath, <<<'PHP'
        return compose('Test')->in('.')->step('Install', fn(Step $step) => $step->composer(install: ['pkg']));
        PHP);

        [$io, $output] = makeIo(OutputInterface::VERBOSITY_VERBOSE);

        $command = new ComposeCommand;
        $command->__invoke(recipe: $recipe, io: $io);

        $text = $output->fetch();

        expect($text)->toContain('installed');
        expect($text)->not->toContain('some deprecation warning');
    });

    it('shows stderr for successful actions at very verbose level', function (): void {
        ProcessExecutor::fake([
            'composer require pkg' => new ActionResult(
                command: ['composer', 'require', 'pkg'],
                exitCode: 0,
                output: 'installed',
                errorOutput: 'some deprecation warning',
                successful: true,
            ),
        ]);

        $recipe = writeRecipe($this->tempPath, <<<'PHP'
        return compose('Test')->in('.')->step('Install', fn(Step $step) => $step->composer(install: ['pkg']));
        PHP);

        [$io, $output] = makeIo(OutputInterface::VERBOSITY_VERY_VERBOSE);

        $command = new ComposeCommand;
        $command->__invoke(recipe: $recipe, io: $io);

        $text = $output->fetch();

        expect($text)->toContain('installed');
        expect($text)->toContain('some deprecation warning');
    });

    it('shows exit code at debug level', function (): void {
        ProcessExecutor::fake([
            'composer require pkg' => ActionResult::success(
                command: ['composer', 'require', 'pkg'],
                output: 'done',
            ),
        ]);

        $recipe = writeRecipe($this->tempPath, <<<'PHP'
        return compose('Test')->in('.')->step('Install', fn(Step $step) => $step->composer(install: ['pkg']));
        PHP);

        [$io, $output] = makeIo(OutputInterface::VERBOSITY_DEBUG);

        $command = new ComposeCommand;
        $command->__invoke(recipe: $recipe, io: $io);

        $text = $output->fetch();

        expect($text)->toContain('exit code: 0');
    });

    it('shows duration on failed actions at verbose level', function (): void {
        ProcessExecutor::fake([
            'composer require bad-pkg' => ActionResult::failure(1, 'Package not found'),
        ]);

        $recipe = writeRecipe($this->tempPath, <<<'PHP'
        return compose('Test')->in('.')->step('Install', fn(Step $step) => $step->composer(install: ['bad-pkg']));
        PHP);

        [$io, $output] = makeIo(OutputInterface::VERBOSITY_VERBOSE);

        $command = new ComposeCommand;
        $command->__invoke(recipe: $recipe, io: $io);

        $text = $output->fetch();

        expect($text)->toContain('Package not found');
        expect($text)->toMatch('/\d+\.\d+s/');
    });

    it('shows step duration at verbose level', function (): void {
        ProcessExecutor::fake([
            'composer require pkg' => ActionResult::success(
                command: ['composer', 'require', 'pkg'],
                output: 'ok',
            ),
        ]);

        $recipe = writeRecipe($this->tempPath, <<<'PHP'
        return compose('Test')->in('.')->step('Install', fn(Step $step) => $step->composer(install: ['pkg']));
        PHP);

        [$io, $output] = makeIo(OutputInterface::VERBOSITY_VERBOSE);

        $command = new ComposeCommand;
        $command->__invoke(recipe: $recipe, io: $io);

        $text = $output->fetch();

        $matches = [];
        preg_match_all('/\d+\.\d+s/', $text, $matches);

        // Action duration (from fake: 0.00s) + step duration
        expect(count($matches[0]))->toBeGreaterThanOrEqual(2);
    });
});
