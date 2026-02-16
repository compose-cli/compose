<?php

use Compose\Events\EventDispatcher;
use Compose\Events\StepCompleted;
use Compose\Events\StepFailed;
use Compose\Events\StepStarting;
use Compose\Execution\ActionResult;
use Compose\Execution\ProcessExecutor;
use Compose\Step;

describe('Runner', function (): void {

    afterEach(function (): void {
        ProcessExecutor::reset();
    });

    it('runs a simple recipe successfully', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')->in('.');
        $recipe->step('Install packages', function (Step $step): void {
            $step->composer(install: ['laravel/framework']);
        });

        $result = $recipe->compose();

        expect($result->successful)->toBeTrue();
        expect($result->stepsCompleted)->toBe(1);
        expect($result->stepsTotal)->toBe(1);

        ProcessExecutor::assertExecuted(['composer', 'require', 'laravel/framework']);
    });

    it('runs multiple steps in order', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe');
        $recipe->step('Step 1', fn (Step $step) => $step->composer(install: ['pkg-a']));
        $recipe->step('Step 2', fn (Step $step) => $step->composer(install: ['pkg-b']));

        $result = $recipe->compose();

        expect($result->successful)->toBeTrue();
        expect($result->stepsCompleted)->toBe(2);

        ProcessExecutor::assertExecuted(['composer', 'require', 'pkg-a']);
        ProcessExecutor::assertExecuted(['composer', 'require', 'pkg-b']);
    });

    it('stops on failure and returns failed result', function (): void {
        ProcessExecutor::fake([
            'composer require fail-pkg' => ActionResult::failure(1, 'Package not found'),
        ]);

        $recipe = compose('Test Recipe');
        $recipe->step('Step 1', fn (Step $step) => $step->composer(install: ['fail-pkg']));
        $recipe->step('Step 2', fn (Step $step) => $step->composer(install: ['never-reached']));

        $result = $recipe->compose();

        expect($result->successful)->toBeFalse();
        expect($result->failedAtStep)->toBe(0);
        expect($result->stepsCompleted)->toBe(0);
        expect($result->stepsTotal)->toBe(1);

        ProcessExecutor::assertNotExecuted(['composer', 'require', 'never-reached']);
    });

    it('rolls back on failure', function (): void {
        $fake = ProcessExecutor::fake([
            'composer require pkg-b' => ActionResult::failure(1, 'fail'),
        ]);

        $recipe = compose('Test Recipe');
        $recipe->step('Failing step', function (Step $step): void {
            $step
                ->composer(install: ['pkg-a'])
                ->composer(install: ['pkg-b']);
        });

        $result = $recipe->compose();

        expect($result->successful)->toBeFalse();

        $stepResult = $result->stepResults[0];
        expect($stepResult->rolledBack)->toBeTrue();

        ProcessExecutor::assertExecuted(['composer', 'remove', 'pkg-a']);
    });

    it('fires events during execution', function (): void {
        ProcessExecutor::fake();

        $dispatcher = new EventDispatcher;
        $events = [];

        $dispatcher->listen(StepStarting::class, function () use (&$events): void {
            $events[] = 'starting';
        });
        $dispatcher->listen(StepCompleted::class, function () use (&$events): void {
            $events[] = 'completed';
        });

        $recipe = compose('Test Recipe');
        $recipe->step('Step 1', fn (Step $step) => $step->composer(install: ['pkg']));

        $recipe->compose($dispatcher);

        expect($events)->toBe(['starting', 'completed']);
    });

    it('fires step failed event on failure', function (): void {
        ProcessExecutor::fake([
            'composer require bad-pkg' => ActionResult::failure(1, 'fail'),
        ]);

        $dispatcher = new EventDispatcher;
        $failedEvent = null;

        $dispatcher->listen(StepFailed::class, function (StepFailed $event) use (&$failedEvent): void {
            $failedEvent = $event;
        });

        $recipe = compose('Test Recipe');
        $recipe->step('Bad step', fn (Step $step) => $step->composer(install: ['bad-pkg']));

        $recipe->compose($dispatcher);

        expect($failedEvent)->not->toBeNull();
        /** @var StepFailed $failedEvent */
        expect($failedEvent->step->name)->toBe('Bad step');
    });

    it('runs before and after callbacks', function (): void {
        ProcessExecutor::fake();
        $callOrder = [];

        $recipe = compose('Test Recipe')
            ->before(function () use (&$callOrder): void {
                $callOrder[] = 'before';
            })
            ->after(function () use (&$callOrder): void {
                $callOrder[] = 'after';
            });

        $recipe->step('Step', function (Step $step) use (&$callOrder): void {
            $callOrder[] = 'step';
            $step->composer(install: ['pkg']);
        });

        $recipe->compose();

        expect($callOrder)->toBe(['before', 'step', 'after']);
    });

    it('does not run after callbacks on failure', function (): void {
        ProcessExecutor::fake([
            'composer require *' => ActionResult::failure(1, 'fail'),
        ]);

        $afterRan = false;

        $recipe = compose('Test Recipe')
            ->after(function () use (&$afterRan): void {
                $afterRan = true;
            });

        $recipe->step('Failing', fn (Step $step) => $step->composer(install: ['pkg']));

        $recipe->compose();

        expect($afterRan)->toBeFalse();
    });

    it('rolls back all previous steps when a later step fails', function (): void {
        ProcessExecutor::fake([
            'composer require --dev fail-pkg' => ActionResult::failure(1, 'fail'),
        ]);

        $recipe = compose('Test Recipe');
        $recipe->step('Step 1', fn (Step $step) => $step->composer(install: ['pkg-a']));
        $recipe->step('Step 2', function (Step $step): void {
            $step->composer(dev: ['fail-pkg']);
        });

        $result = $recipe->compose();

        expect($result->successful)->toBeFalse();
        expect($result->failedAtStep)->toBe(1);

        ProcessExecutor::assertExecuted(['composer', 'remove', 'pkg-a']);
    });

    it('uses project directory for steps after base clone', function (): void {
        $fake = ProcessExecutor::fake();

        $recipe = compose('My App')
            ->in('/tmp/target')
            ->base('https://github.com/laravel/laravel.git', '11.x');

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $recipe->compose();

        $executed = $fake->executed();

        expect($executed[0]['cwd'])->toBe('/tmp/target');
        expect($executed[0]['command'])->toContain('my-app');

        expect($executed[1]['cwd'])->toBe('/tmp/target'.DIRECTORY_SEPARATOR.'my-app');
    });

});
