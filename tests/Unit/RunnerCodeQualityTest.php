<?php

use Compose\Events\ActionCompleted;
use Compose\Events\ActionExecuting;
use Compose\Events\ActionFailed;
use Compose\Events\EventDispatcher;
use Compose\Execution\ActionResult;
use Compose\Execution\ProcessExecutor;
use Compose\Step;

describe('Runner code quality', function (): void {

    afterEach(function (): void {
        ProcessExecutor::reset();
    });

    it('runs pint after each step when format is enabled and pint is installed', function (): void {
        ProcessExecutor::fake();

        $this->createFile('vendor/bin/pint', '#!/usr/bin/env php');

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->format();

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        ProcessExecutor::assertExecuted(['php', 'vendor/bin/pint']);
    });

    it('runs rector after each step when format with rector is enabled and rector is installed', function (): void {
        ProcessExecutor::fake();

        $this->createFile('vendor/bin/rector', '#!/usr/bin/env php');

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->format(pint: false, rector: true);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        ProcessExecutor::assertExecuted(['php', 'vendor/bin/rector', 'process']);
    });

    it('runs rector before pint when both are enabled', function (): void {
        $fake = ProcessExecutor::fake();

        $this->createFile('vendor/bin/pint', '#!/usr/bin/env php');
        $this->createFile('vendor/bin/rector', '#!/usr/bin/env php');

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->format(pint: true, rector: true);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        $executed = $fake->executed();
        $commands = array_map(fn ($cmd) => implode(' ', $cmd['command']), $executed);

        $rectorIndex = array_search('php vendor/bin/rector process', $commands);
        $pintIndex = array_search('php vendor/bin/pint', $commands);

        expect($rectorIndex)->toBeLessThan($pintIndex);
    });

    it('does not run pint or rector when format is not enabled', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')
            ->in($this->tempPath);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        ProcessExecutor::assertNotExecuted(['php', 'vendor/bin/pint']);
        ProcessExecutor::assertNotExecuted(['php', 'vendor/bin/rector', 'process']);
    });

    it('skips code quality for the base clone step', function (): void {
        $fake = ProcessExecutor::fake();

        // base() slugifies "My App" to "my-app", so the project dir is tempPath/my-app
        $this->createFile('my-app/vendor/bin/pint', '#!/usr/bin/env php');

        $recipe = compose('My App')
            ->in($this->tempPath)
            ->format()
            ->base('https://github.com/laravel/laravel.git', '11.x');

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $result = $recipe->run();

        $executed = $fake->executed();
        $pintCommands = array_filter($executed, fn ($cmd) => $cmd['command'] === ['php', 'vendor/bin/pint']);

        // Pint should run only for the user step, not the clone step
        expect($pintCommands)->toHaveCount(1);
    });

    it('runs code quality before auto-commit', function (): void {
        $fake = ProcessExecutor::fake();

        $this->createFile('vendor/bin/pint', '#!/usr/bin/env php');

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->format()
            ->commit(automatically: true);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        $executed = $fake->executed();
        $commands = array_map(fn ($cmd) => implode(' ', $cmd['command']), $executed);

        $pintIndex = array_search('php vendor/bin/pint', $commands);
        $commitIndex = array_search('git add -A', $commands);

        expect($pintIndex)->toBeLessThan($commitIndex);
    });

    it('warns when pint is not installed', function (): void {
        ProcessExecutor::fake();

        $dispatcher = new EventDispatcher;
        $failedEvents = [];

        $dispatcher->listen(ActionFailed::class, function (ActionFailed $event) use (&$failedEvents): void {
            if ($event->codeQuality) {
                $failedEvents[] = $event;
            }
        });

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->format();

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $result = $recipe->run($dispatcher);

        expect($result->successful)->toBeTrue();
        expect($failedEvents)->toHaveCount(1);
        expect($failedEvents[0]->warned)->toBeTrue();
        expect($failedEvents[0]->result->errorOutput)->toContain('Laravel Pint is not installed');

        ProcessExecutor::assertNotExecuted(['php', 'vendor/bin/pint']);
    });

    it('warns when rector is not installed', function (): void {
        ProcessExecutor::fake();

        $dispatcher = new EventDispatcher;
        $failedEvents = [];

        $dispatcher->listen(ActionFailed::class, function (ActionFailed $event) use (&$failedEvents): void {
            if ($event->codeQuality) {
                $failedEvents[] = $event;
            }
        });

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->format(pint: false, rector: true);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $result = $recipe->run($dispatcher);

        expect($result->successful)->toBeTrue();
        expect($failedEvents)->toHaveCount(1);
        expect($failedEvents[0]->result->errorOutput)->toContain('Rector is not installed');

        ProcessExecutor::assertNotExecuted(['php', 'vendor/bin/rector', 'process']);
    });

    it('fires codeQuality events when running quality tools', function (): void {
        ProcessExecutor::fake();

        $this->createFile('vendor/bin/pint', '#!/usr/bin/env php');

        $dispatcher = new EventDispatcher;
        $events = [];

        $dispatcher->listen(ActionExecuting::class, function (ActionExecuting $event) use (&$events): void {
            if ($event->codeQuality) {
                $events[] = 'executing:'.$event->action->describe();
            }
        });
        $dispatcher->listen(ActionCompleted::class, function (ActionCompleted $event) use (&$events): void {
            if ($event->codeQuality) {
                $events[] = 'completed:'.$event->action->describe();
            }
        });

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->format();

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $recipe->run($dispatcher);

        expect($events)->toBe([
            'executing:pint (format)',
            'completed:pint (format)',
        ]);
    });

    it('treats code quality failure as a warning', function (): void {
        ProcessExecutor::fake([
            'php vendor/bin/pint' => ActionResult::failure(1, 'pint failed'),
        ]);

        $this->createFile('vendor/bin/pint', '#!/usr/bin/env php');

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->format();

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();
    });

    it('runs quality tools after each step in a multi-step recipe', function (): void {
        $fake = ProcessExecutor::fake();

        $this->createFile('vendor/bin/pint', '#!/usr/bin/env php');

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->format();

        $recipe->step('Step 1', fn (Step $step) => $step->composer(install: ['pkg-a']));
        $recipe->step('Step 2', fn (Step $step) => $step->composer(install: ['pkg-b']));

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        $executed = $fake->executed();
        $pintCommands = array_filter($executed, fn ($cmd) => $cmd['command'] === ['php', 'vendor/bin/pint']);

        expect($pintCommands)->toHaveCount(2);
    });

    it('does not run quality tools when step fails', function (): void {
        ProcessExecutor::fake([
            'composer require bad-pkg' => ActionResult::failure(1, 'fail'),
        ]);

        $this->createFile('vendor/bin/pint', '#!/usr/bin/env php');

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->format();

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['bad-pkg']));

        $result = $recipe->run();

        expect($result->successful)->toBeFalse();

        ProcessExecutor::assertNotExecuted(['php', 'vendor/bin/pint']);
    });

});

describe('Runner code quality config', function (): void {

    it('passes format flags through to RecipeConfig', function (): void {
        $recipe = compose('Test Recipe')
            ->in('.')
            ->format(pint: true, rector: true);

        $config = $recipe->toConfig();

        expect($config->formatWithPint)->toBeTrue();
        expect($config->formatWithRector)->toBeTrue();
    });

    it('defaults format flags to false', function (): void {
        $recipe = compose('Test Recipe')->in('.');

        $config = $recipe->toConfig();

        expect($config->formatWithPint)->toBeFalse();
        expect($config->formatWithRector)->toBeFalse();
    });

    it('can override format flags via withOverrides', function (): void {
        $recipe = compose('Test Recipe')
            ->in('.')
            ->format(pint: true, rector: true);

        $config = $recipe->toConfig();
        $overridden = $config->withOverrides(formatWithPint: false, formatWithRector: false);

        expect($overridden->formatWithPint)->toBeFalse();
        expect($overridden->formatWithRector)->toBeFalse();
    });

    it('format enables pint by default', function (): void {
        $recipe = compose('Test Recipe')
            ->in('.')
            ->format();

        $config = $recipe->toConfig();

        expect($config->formatWithPint)->toBeTrue();
        expect($config->formatWithRector)->toBeFalse();
    });

});
