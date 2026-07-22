<?php

use Compose\Builders\Artisan;
use Compose\Enums\FailureStrategy;
use Compose\Enums\TaskType;
use Compose\Events\ActionCompleted;
use Compose\Events\ActionExecuting;
use Compose\Events\ActionFailed;
use Compose\Events\EventDispatcher;
use Compose\Events\RollbackCompleted;
use Compose\Events\RollbackStarting;
use Compose\Events\StepCompleted;
use Compose\Events\StepFailed;
use Compose\Events\StepStarting;
use Compose\Exceptions\DangerousPathException;
use Compose\Execution\ActionResult;
use Compose\Execution\ProcessExecutor;
use Compose\Step;
use Symfony\Component\Process\Process;

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

        $result = $recipe->run();

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

        $result = $recipe->run();

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

        $result = $recipe->run();

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

        $result = $recipe->run();

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

        $recipe->run($dispatcher);

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

        $recipe->run($dispatcher);

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

        $recipe->run();

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

        $recipe->run();

        expect($afterRan)->toBeFalse();
    });

    it('does not roll back previous steps by default when a later step fails', function (): void {
        ProcessExecutor::fake([
            'composer require --dev fail-pkg' => ActionResult::failure(1, 'fail'),
        ]);

        $recipe = compose('Test Recipe');
        $recipe->step('Step 1', fn (Step $step) => $step->composer(install: ['pkg-a']));
        $recipe->step('Step 2', function (Step $step): void {
            $step->composer(dev: ['fail-pkg']);
        });

        $result = $recipe->run();

        expect($result->successful)->toBeFalse();
        expect($result->failedAtStep)->toBe(1);

        ProcessExecutor::assertNotExecuted(['composer', 'remove', 'pkg-a']);
    });

    it('fires RollbackStarting and RollbackCompleted events when rolling back all previous steps', function (): void {
        ProcessExecutor::fake([
            'composer require --dev fail-pkg' => ActionResult::failure(1, 'fail'),
        ]);

        $dispatcher = new EventDispatcher;
        $events = [];

        $dispatcher->listen(RollbackStarting::class, function () use (&$events): void {
            $events[] = 'rollback-starting';
        });
        $dispatcher->listen(RollbackCompleted::class, function () use (&$events): void {
            $events[] = 'rollback-completed';
        });

        $recipe = compose('Test Recipe');
        $recipe->step('Step 1', fn (Step $step) => $step->composer(install: ['pkg-a']));
        $recipe->step('Step 2', function (Step $step): void {
            $step->composer(dev: ['fail-pkg']);
        }, onFailure: FailureStrategy::RollbackAll);

        $recipe->run($dispatcher);

        expect($events)->toContain('rollback-starting');
        expect($events)->toContain('rollback-completed');
    });

    it('rolls back all previous steps when step uses RollbackAll strategy', function (): void {
        ProcessExecutor::fake([
            'composer require --dev fail-pkg' => ActionResult::failure(1, 'fail'),
        ]);

        $recipe = compose('Test Recipe');
        $recipe->step('Step 1', fn (Step $step) => $step->composer(install: ['pkg-a']));
        $recipe->step('Step 2', function (Step $step): void {
            $step->composer(dev: ['fail-pkg']);
        }, onFailure: FailureStrategy::RollbackAll);

        $result = $recipe->run();

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

        $recipe->run();

        $executed = $fake->executed();

        expect($executed[0]['cwd'])->toBe('/tmp/target');
        expect($executed[0]['command'])->toContain('my-app');

        expect($executed[1]['cwd'])->toBe('/tmp/target'.DIRECTORY_SEPARATOR.'my-app');
    });

    it('continues execution when step has FailureStrategy::Continue', function (): void {
        ProcessExecutor::fake([
            'npm uninstall tailwindcss' => ActionResult::failure(1, 'not installed'),
        ]);

        $recipe = compose('Test Recipe');
        $recipe->step('Cleanup', function (Step $step): void {
            $step->node(remove: ['tailwindcss']);
        }, onFailure: FailureStrategy::Continue);
        $recipe->step('Install', fn (Step $step) => $step->node(install: ['unocss']));

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();
        expect($result->stepsCompleted)->toBe(2);
        expect($result->hasWarnings)->toBeTrue();
        expect($result->warnings)->toHaveCount(1);

        ProcessExecutor::assertExecuted(['npm', 'install', 'unocss']);
    });

    it('continues execution when action has allowFailure', function (): void {
        ProcessExecutor::fake([
            'npm uninstall tailwindcss' => ActionResult::failure(1, 'not installed'),
        ]);

        $recipe = compose('Test Recipe');
        $recipe->step('Swap CSS', function (Step $step): void {
            $step
                ->node(remove: ['tailwindcss'], allowFailure: true)
                ->node(install: ['unocss']);
        });

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();
        expect($result->hasWarnings)->toBeTrue();
        expect($result->warnings)->toHaveCount(1);

        ProcessExecutor::assertExecuted(['npm', 'install', 'unocss']);
    });

    it('does not rollback warned actions', function (): void {
        ProcessExecutor::fake([
            'composer require fail-pkg' => ActionResult::failure(1, 'fail'),
        ]);

        $recipe = compose('Test Recipe');
        $recipe->step('Mixed', function (Step $step): void {
            $step
                ->composer(install: ['good-pkg'])
                ->composer(install: ['fail-pkg'], allowFailure: true)
                ->composer(install: ['another-pkg']);
        });

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        ProcessExecutor::assertNotExecuted(['composer', 'remove', 'fail-pkg']);
        ProcessExecutor::assertNotExecuted(['composer', 'remove', 'good-pkg']);
    });

    it('still aborts on failure when action does not allowFailure and step is Abort', function (): void {
        ProcessExecutor::fake([
            'composer require bad-pkg' => ActionResult::failure(1, 'fail'),
        ]);

        $recipe = compose('Test Recipe');
        $recipe->step('Install', function (Step $step): void {
            $step->composer(install: ['bad-pkg']);
        });

        $result = $recipe->run();

        expect($result->successful)->toBeFalse();
        expect($result->hasWarnings)->toBeFalse();
    });

    it('fires warned ActionFailed event for allowed failures', function (): void {
        ProcessExecutor::fake([
            'npm uninstall tailwindcss' => ActionResult::failure(1, 'fail'),
        ]);

        $dispatcher = new EventDispatcher;
        $warnedEvents = [];

        $dispatcher->listen(ActionFailed::class, function (ActionFailed $event) use (&$warnedEvents): void {
            if ($event->warned) {
                $warnedEvents[] = $event;
            }
        });

        $recipe = compose('Test Recipe');
        $recipe->step('Cleanup', function (Step $step): void {
            $step->node(remove: ['tailwindcss'], allowFailure: true);
        });

        $recipe->run($dispatcher);

        expect($warnedEvents)->toHaveCount(1);
    });

    it('reports warnings in step results', function (): void {
        ProcessExecutor::fake([
            'npm uninstall tailwindcss' => ActionResult::failure(1, 'not found'),
        ]);

        $recipe = compose('Test Recipe');
        $recipe->step('Cleanup', function (Step $step): void {
            $step
                ->node(remove: ['tailwindcss'])
                ->node(install: ['unocss']);
        }, onFailure: FailureStrategy::Continue);

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        $stepResult = $result->stepResults[0];
        expect($stepResult->hasWarnings)->toBeTrue();
        expect($stepResult->warnings)->toHaveCount(1);
        expect($stepResult->warnings[0]->warned)->toBeTrue();
    });

});

describe('Runner auto-commit', function (): void {

    afterEach(function (): void {
        ProcessExecutor::reset();
    });

    it('runs git init and auto-commits after each step', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->commit(automatically: true);

        $recipe->step('Install', function (Step $step): void {
            $step
                ->composer(install: ['pkg'])
                ->create('installed.txt', 'pkg');
        });

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();
        expect(is_dir($this->tempPath.DIRECTORY_SEPARATOR.'.git'))->toBeTrue();

        $log = new Process(['git', 'log', '-1', '--pretty=%s'], $this->tempPath);
        $log->run();
        expect(trim($log->getOutput()))->toBe('compose: Install');
    });

    it('uses step message for auto-commit when defined', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->commit(automatically: true);

        $recipe->step('Install', function (Step $step): void {
            $step
                ->composer(install: ['pkg'])
                ->create('installed.txt', 'pkg');
        }, message: 'feat: install packages');

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        $log = new Process(['git', 'log', '-1', '--pretty=%s'], $this->tempPath);
        $log->run();
        expect(trim($log->getOutput()))->toBe('feat: install packages');
    });

    it('uses default message format when no step message is set', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->commit(automatically: true);

        $recipe->step('Setup frontend', function (Step $step): void {
            $step
                ->node(install: ['vue'])
                ->create('frontend.txt', 'vue');
        });

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        $log = new Process(['git', 'log', '-1', '--pretty=%s'], $this->tempPath);
        $log->run();
        expect(trim($log->getOutput()))->toBe('compose: Setup frontend');
    });

    it('does not auto-commit when commit is disabled', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->commit(automatically: false);

        $recipe->step('Install', function (Step $step): void {
            $step
                ->composer(install: ['pkg'])
                ->create('installed.txt', 'pkg');
        });

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();
        expect(is_dir($this->tempPath.DIRECTORY_SEPARATOR.'.git'))->toBeFalse();
    });

    it('skips auto-commit for base clone step', function (): void {
        ProcessExecutor::fake();

        $target = $this->tempPath.DIRECTORY_SEPARATOR.'target';
        $project = $target.DIRECTORY_SEPARATOR.'my-app';
        mkdir($project, 0755, true);

        (new Process(['git', 'init'], $project))->run();
        (new Process(['git', 'config', 'user.name', 'Test'], $project))->run();
        (new Process(['git', 'config', 'user.email', 'test@example.com'], $project))->run();
        (new Process(['git', 'commit', '--allow-empty', '-m', 'clone'], $project))->run();

        $recipe = compose('My App')
            ->in($target)
            ->commit(automatically: true)
            ->base('https://github.com/laravel/laravel.git', '11.x');

        $recipe->step('Install', function (Step $step): void {
            $step
                ->composer(install: ['pkg'])
                ->create('installed.txt', 'pkg');
        });

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        $log = new Process(['git', 'log', '--pretty=%s'], $project);
        $log->run();
        $messages = array_values(array_filter(array_map(trim(...), explode("\n", trim($log->getOutput())))));

        // Pre-existing clone commit + one Install auto-commit (clone step itself is not auto-committed).
        expect($messages)->toBe([
            'compose: Install',
            'clone',
        ]);
    });

    it('skips auto-commit when step already contains a manual GitCommit', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->commit(automatically: true);

        $recipe->step('Install', function (Step $step): void {
            $step
                ->composer(install: ['pkg'])
                ->create('installed.txt', 'pkg')
                ->commit('manual: installed packages');
        });

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        $log = new Process(['git', 'log', '--pretty=%s'], $this->tempPath);
        $log->run();
        $messages = array_values(array_filter(array_map(trim(...), explode("\n", trim($log->getOutput())))));

        expect($messages)->toHaveCount(1);
        expect($messages[0])->toBe('manual: installed packages');
    });

    it('does not fail the recipe when there is nothing to commit', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->commit(automatically: true);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();
    });

    it('fires events for auto-commit actions', function (): void {
        ProcessExecutor::fake();

        $dispatcher = new EventDispatcher;
        $events = [];

        $dispatcher->listen(ActionExecuting::class, function (ActionExecuting $event) use (&$events): void {
            $events[] = 'executing:'.$event->action->describe();
        });
        $dispatcher->listen(ActionCompleted::class, function (ActionCompleted $event) use (&$events): void {
            $events[] = 'completed:'.$event->action->describe();
        });

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->commit(automatically: true);

        $recipe->step('Install', function (Step $step): void {
            $step
                ->composer(install: ['pkg'])
                ->create('installed.txt', 'pkg');
        });

        $recipe->run($dispatcher);

        $gitEvents = array_values(array_filter($events, fn ($e) => str_contains($e, 'git')));

        expect($gitEvents)->toContain('executing:git commit -m compose: Install');
        expect($gitEvents)->toContain('completed:git commit -m compose: Install');
    });

    it('auto-commits after each step in a multi-step recipe', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->commit(automatically: true);

        $recipe->step('Step 1', function (Step $step): void {
            $step
                ->composer(install: ['pkg-a'])
                ->create('a.txt', 'a');
        });
        $recipe->step('Step 2', function (Step $step): void {
            $step
                ->composer(install: ['pkg-b'])
                ->create('b.txt', 'b');
        });

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        $log = new Process(['git', 'log', '--pretty=%s'], $this->tempPath);
        $log->run();
        $messages = array_values(array_filter(array_map(trim(...), explode("\n", trim($log->getOutput())))));

        expect($messages)->toBe([
            'compose: Step 2',
            'compose: Step 1',
        ]);
    });

    it('rolls back auto-commits when RollbackAll is triggered', function (): void {
        ProcessExecutor::fake([
            'composer require pkg-b' => ActionResult::failure(1, 'fail'),
        ]);

        $recipe = compose('Test Recipe')
            ->in($this->tempPath)
            ->commit(automatically: true);

        $recipe->step('Step 1', function (Step $step): void {
            $step
                ->composer(install: ['pkg-a'])
                ->create('a.txt', 'a');
        });
        $recipe->step('Step 2', function (Step $step): void {
            $step->composer(install: ['pkg-b']);
        }, onFailure: FailureStrategy::RollbackAll);

        $result = $recipe->run();

        expect($result->successful)->toBeFalse();

        $log = new Process(['git', 'log', '--oneline'], $this->tempPath);
        $log->run();

        // First-commit rollback deletes HEAD, so rev-parse/log should fail or be empty.
        $head = new Process(['git', 'rev-parse', 'HEAD'], $this->tempPath);
        $head->run();
        expect($head->isSuccessful())->toBeFalse();

        // Working tree file from step 1 remains after mixed/unborn reset; create rollback deletes it.
        expect(file_exists($this->tempPath.DIRECTORY_SEPARATOR.'a.txt'))->toBeFalse();
    });

});

describe('Runner fresh guard', function (): void {

    afterEach(function (): void {
        ProcessExecutor::reset();
    });

    it('throws when fresh mode targets the current working directory', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')
            ->in('.', fresh: true);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $recipe->run();
    })->throws(DangerousPathException::class);

    it('throws when fresh mode targets getcwd()', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')
            ->in((string) getcwd(), fresh: true);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $recipe->run();
    })->throws(DangerousPathException::class);

    it('throws when fresh mode has no working directory', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')
            ->in(fresh: true);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $recipe->run();
    })->throws(DangerousPathException::class);

    it('throws when fresh mode targets a parent of the current working directory', function (): void {
        ProcessExecutor::fake();

        $parent = dirname((string) getcwd());

        $recipe = compose('Test Recipe')
            ->in($parent, fresh: true);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $recipe->run();
    })->throws(DangerousPathException::class);

    it('throws when fresh mode targets an empty string', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')
            ->in('', fresh: true);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $recipe->run();
    })->throws(DangerousPathException::class);

    it('allows fresh mode with a valid subdirectory', function (): void {
        ProcessExecutor::fake();

        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'compose_fresh_test_'.uniqid();
        mkdir($tempDir, 0755, true);

        $recipe = compose('Test Recipe')
            ->in($tempDir, fresh: true);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        if (is_dir($tempDir)) {
            rmdir($tempDir);
        }
    });

});

describe('Runner preflight', function (): void {

    afterEach(function (): void {
        ProcessExecutor::reset();
    });

    it('runs artisan actions when preflight passes', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')->in('.');
        $recipe->step('Artisan', function (Step $step): void {
            $step->artisan('migrate');
        });

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        ProcessExecutor::assertExecuted(['php', 'artisan', '--version']);
        ProcessExecutor::assertExecuted(['php', 'artisan', 'migrate']);
    });

    it('fails the step when artisan preflight fails', function (): void {
        ProcessExecutor::fake([
            'php artisan --version' => ActionResult::failure(1, 'php: command not found'),
        ]);

        $recipe = compose('Test Recipe')->in('.');
        $recipe->step('Artisan', function (Step $step): void {
            $step->artisan('migrate');
        });

        $result = $recipe->run();

        expect($result->successful)->toBeFalse();

        ProcessExecutor::assertNotExecuted(['php', 'artisan', 'migrate']);
    });

    it('only runs preflight once for multiple artisan actions in a step', function (): void {
        $fake = ProcessExecutor::fake();

        $recipe = compose('Test Recipe')->in('.');
        $recipe->step('Artisan', function (Step $step): void {
            $step->artisan(fn (Artisan $a) => $a
                ->run('make:model Team')
                ->run('make:controller TeamController')
            );
        });

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        $executed = $fake->executed();
        $preflightCommands = array_filter(
            $executed,
            fn (array $cmd): bool => $cmd['command'] === ['php', 'artisan', '--version'],
        );

        expect($preflightCommands)->toHaveCount(1);
    });

    it('does not run preflight for non-artisan actions', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Test Recipe')->in('.');
        $recipe->step('Composer', function (Step $step): void {
            $step->composer(install: ['laravel/framework']);
        });

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        ProcessExecutor::assertNotExecuted(['php', 'artisan', '--version']);
    });

});

describe('Runner feature mode', function (): void {

    afterEach(function (): void {
        ProcessExecutor::reset();
    });

    it('does not run git init for NewFeature type', function (): void {
        ProcessExecutor::fake();

        (new Process(['git', 'init'], $this->tempPath))->run();
        (new Process(['git', 'config', 'user.name', 'Test'], $this->tempPath))->run();
        (new Process(['git', 'config', 'user.email', 'test@example.com'], $this->tempPath))->run();
        (new Process(['git', 'commit', '--allow-empty', '--no-gpg-sign', '-m', 'seed'], $this->tempPath))->run();

        $before = trim((new Process(['git', 'rev-parse', 'HEAD'], $this->tempPath))->mustRun()->getOutput());

        $recipe = compose('Feature Recipe', type: TaskType::NewFeature)
            ->in($this->tempPath)
            ->commit(automatically: true);

        $recipe->step('Install', function (Step $step): void {
            $step
                ->composer(install: ['pkg'])
                ->create('feature.txt', 'pkg');
        });

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();

        $log = new Process(['git', 'log', '--pretty=%s'], $this->tempPath);
        $log->run();
        $messages = array_values(array_filter(array_map(trim(...), explode("\n", trim($log->getOutput())))));

        expect($messages)->toBe([
            'compose: Install',
            'seed',
        ]);

        $afterParent = trim((new Process(['git', 'rev-parse', 'HEAD~1'], $this->tempPath))->mustRun()->getOutput());
        expect($afterParent)->toBe($before);
    });

    it('does not run git init for Refactoring type', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Refactor Recipe', type: TaskType::Refactoring)
            ->in($this->tempPath)
            ->commit(automatically: true);

        $recipe->step('Cleanup', fn (Step $step) => $step->composer(remove: ['old-pkg']));

        $result = $recipe->run();

        expect($result->successful)->toBeTrue();
        expect(is_dir($this->tempPath.DIRECTORY_SEPARATOR.'.git'))->toBeFalse();
    });

    it('does not delete directory for fresh mode with non-NewProject type', function (): void {
        ProcessExecutor::fake();

        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'compose_feature_fresh_'.uniqid();
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.DIRECTORY_SEPARATOR.'marker.txt', 'exists');

        $recipe = compose('Feature Recipe', type: TaskType::NewProject)
            ->in($tempDir, fresh: true);

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));
        $recipe->run();

        expect(file_exists($tempDir.DIRECTORY_SEPARATOR.'marker.txt'))->toBeFalse();

        if (is_dir($tempDir)) {
            rmdir($tempDir);
        }
    });

    it('throws when using base() with NewFeature type', function (): void {
        compose('Feature Recipe', type: TaskType::NewFeature)
            ->base('https://github.com/laravel/laravel.git');
    })->throws(LogicException::class, 'base() can only be used with TaskType::NewProject');

    it('throws when using fresh mode with NewFeature type', function (): void {
        compose('Feature Recipe', type: TaskType::NewFeature)
            ->in('.', fresh: true);
    })->throws(LogicException::class, 'fresh mode can only be used with TaskType::NewProject');

    it('throws when using branch() with NewProject type', function (): void {
        compose('New Project', type: TaskType::NewProject)
            ->branch('feature/test');
    })->throws(LogicException::class, 'branch() is for existing projects');

    it('allows branch() with NewFeature type', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Feature Recipe', type: TaskType::NewFeature)
            ->in('.')
            ->branch('feature/permissions');

        $config = $recipe->toConfig();

        expect($config->steps)->toHaveCount(1);
        expect($config->steps[0]->name)->toBe('Switch to branch');
    });

    it('prepends branch step before user steps', function (): void {
        ProcessExecutor::fake();

        $recipe = compose('Feature Recipe', type: TaskType::NewFeature)
            ->in('.');

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));
        $recipe->branch('feature/test');

        $config = $recipe->toConfig();

        expect($config->steps)->toHaveCount(2);
        expect($config->steps[0]->name)->toBe('Switch to branch');
        expect($config->steps[1]->name)->toBe('Install');
    });

    it('passes taskType through RecipeConfig', function (): void {
        $recipe = compose('Feature Recipe', type: TaskType::NewFeature)->in('.');

        $config = $recipe->toConfig();

        expect($config->taskType)->toBe(TaskType::NewFeature);
        expect($config->isNewProject)->toBeFalse();
    });

    it('defaults to NewProject taskType', function (): void {
        $recipe = compose('Default Recipe')->in('.');

        $config = $recipe->toConfig();

        expect($config->taskType)->toBe(TaskType::NewProject);
        expect($config->isNewProject)->toBeTrue();
    });

});
