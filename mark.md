# Implement Phase 3.1: Artisan Actions

## Overview

Adds `Step::artisan()` (string and closure forms) backed by a new `ArtisanAction` class and `ArtisanBuilder` for batch commands, plus a preflight check that artisan exists before execution. No Step-level convenience methods (`migrate()`, `seed()`) -- all artisan operations go through the builder.

## Target API

```php
// Simple string
$step->artisan('vendor:publish --tag=cashier-migrations');

// Batch builder (all artisan ops live here)
$step->artisan(fn (ArtisanBuilder $a) => $a
    ->run('make:controller TeamController --api')
    ->run('make:resource TeamResource')
    ->migrate(fresh: true, seed: true)
    ->seed('RolesSeeder', 'TeamSeeder')
    ->publish(provider: 'Spatie\\Permission\\PermissionServiceProvider')
    ->publish(tag: 'permission-migrations')
    ->make(resource: 'model', name: 'Team -mfs')
    ->makeModel(name: 'Team', migration: true, factory: true, seeder: true)
);
```

## Preflight Check

A `preflight()` method on the base `Action` class returns an optional `PendingCommand` that must succeed before any action of that type executes. `ArtisanAction` overrides it to return `php artisan --version`. The `ExecuteActions` pipe runs preflight once per action class. Since it goes through `ProcessExecutor`, it's automatically testable via `ProcessExecutor::fake()`.

---

## Changes Summary

### New Files

1. `src/Enums/ArtisanOperation.php` -- new Operation enum
2. `src/Actions/Laravel/ArtisanAction.php` -- concrete action class
3. `src/Builders/ArtisanBuilder.php` -- batch builder
4. `tests/Unit/ArtisanActionTest.php` -- action tests
5. `tests/Unit/ArtisanBuilderTest.php` -- builder tests

### Modified Files

6. `src/RecipeContext.php` -- add `phpBinary` property
7. `src/Actions/Action.php` -- add `artisan()` helper + `preflight()` method
8. `src/Step.php` -- add `artisan(string|Closure)` method
9. `src/Execution/Pipes/ExecuteActions.php` -- add preflight logic
10. `tests/Pest.php` -- add `phpBinary` to `context()` helper
11. `tests/Unit/StepTest.php` -- add artisan tests
12. `tests/Unit/RunnerTest.php` -- add preflight integration tests

---

## Full Implementation

All code below was written and all tests passed before the project files were deleted.

### 1. `src/Enums/ArtisanOperation.php` (NEW)

```php
<?php

declare(strict_types=1);

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum ArtisanOperation: string implements Operation
{
    case Run = 'artisan:run';
}
```

### 2. `src/Actions/Laravel/ArtisanAction.php` (NEW)

```php
<?php

declare(strict_types=1);

namespace Compose\Actions\Laravel;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\ArtisanOperation;

class ArtisanAction extends Action
{
    public function __construct(
        public readonly string $command,
    ) {}

    public function type(): ArtisanOperation
    {
        return ArtisanOperation::Run;
    }

    public function command(): PendingCommand
    {
        return $this->artisan(...explode(' ', $this->command));
    }

    #[\Override]
    public function preflight(): PendingCommand
    {
        return $this->artisan('--version');
    }
}
```

### 3. `src/Builders/ArtisanBuilder.php` (NEW)

```php
<?php

declare(strict_types=1);

namespace Compose\Builders;

use Closure;
use Compose\Actions\Laravel\ArtisanAction;

class ArtisanBuilder
{
    /** @var string[] */
    protected array $commands = [];

    /**
     * Add a raw artisan command.
     */
    public function run(string $command): static
    {
        $this->commands[] = $command;

        return $this;
    }

    /**
     * Run a migration.
     */
    public function migrate(bool $fresh = false, bool $seed = false): static
    {
        $command = $fresh ? 'migrate:fresh' : 'migrate';

        if ($seed) {
            $command .= ' --seed';
        }

        return $this->run($command);
    }

    /**
     * Seed the database.
     *
     * When called with no arguments, runs `db:seed`.
     * When called with seeder names, runs `db:seed --class=<name>` for each.
     */
    public function seed(string ...$seeders): static
    {
        if ($seeders === []) {
            return $this->run('db:seed');
        }

        foreach ($seeders as $seeder) {
            $this->run("db:seed --class={$seeder}");
        }

        return $this;
    }

    /**
     * Publish vendor assets.
     */
    public function publish(?string $provider = null, ?string $tag = null): static
    {
        $command = 'vendor:publish';

        if ($provider !== null) {
            $command .= " --provider={$provider}";
        }

        if ($tag !== null) {
            $command .= " --tag={$tag}";
        }

        return $this->run($command);
    }

    /**
     * Run a make command.
     */
    public function make(string $resource, string $name): static
    {
        return $this->run("make:{$resource} {$name}");
    }

    /**
     * Make a model with optional related files.
     */
    public function makeModel(
        string $name,
        bool $migration = false,
        bool $factory = false,
        bool $seeder = false,
    ): static {
        $flags = '';

        if ($migration) {
            $flags .= ' -m';
        }

        if ($factory) {
            $flags .= ' -f';
        }

        if ($seeder) {
            $flags .= ' -s';
        }

        return $this->run("make:model {$name}{$flags}");
    }

    /**
     * Conditionally add commands.
     */
    public function when(bool|Closure $condition, Closure $callback): static
    {
        $result = $condition instanceof Closure ? $condition() : $condition;

        if ($result) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Compile the collected commands into ArtisanAction instances.
     *
     * @return ArtisanAction[]
     */
    public function actions(): array
    {
        return array_map(
            fn (string $command): ArtisanAction => new ArtisanAction($command),
            $this->commands,
        );
    }
}
```

### 4. `src/RecipeContext.php` (MODIFIED -- add `phpBinary`)

Add `phpBinary` parameter after `composerBinary` in the constructor, and carry it forward in `withWorkingDirectory()`:

```php
public function __construct(
    public readonly string $composerBinary = 'composer',
    public readonly string $phpBinary = 'php',       // <-- ADD THIS
    public readonly string $gitBinary = 'git',
    public readonly Node $nodeManager = Node::Npm,
    public readonly ?string $workingDirectory = null,
) {}

public function withWorkingDirectory(?string $directory): static
{
    return new static(
        composerBinary: $this->composerBinary,
        phpBinary: $this->phpBinary,                  // <-- ADD THIS
        gitBinary: $this->gitBinary,
        nodeManager: $this->nodeManager,
        workingDirectory: $directory,
    );
}
```

### 5. `src/Actions/Action.php` (MODIFIED -- add `preflight()` + `artisan()`)

Add `preflight()` method after `canBeRolledBack()`:

```php
/**
 * A command that must succeed before any action of this type executes.
 *
 * Return null if no preflight check is needed.
 */
public function preflight(): ?PendingCommand
{
    return null;
}
```

Add `artisan()` helper at the end of the class alongside `composer()`, `node()`, `git()`:

```php
/**
 * Create a pending command for php artisan.
 */
protected function artisan(string ...$subcommand): PendingCommand
{
    return new PendingCommand($this->context()->phpBinary, 'artisan', ...$subcommand);
}
```

### 6. `src/Step.php` (MODIFIED -- add `artisan()` method)

Add imports:

```php
use Compose\Actions\Laravel\ArtisanAction;
use Compose\Builders\ArtisanBuilder;
```

Add method before `commit()`:

```php
/**
 * Add artisan operations to this step.
 *
 * When passed a string, creates a single artisan command.
 * When passed a closure, receives an ArtisanBuilder for batch operations.
 */
public function artisan(string|Closure $command): static
{
    if (is_string($command)) {
        $this->operations[] = new ArtisanAction($command);

        return $this;
    }

    $builder = new ArtisanBuilder;
    $command($builder);

    foreach ($builder->actions() as $action) {
        $this->operations[] = $action;
    }

    return $this;
}
```

### 7. `src/Execution/Pipes/ExecuteActions.php` (MODIFIED -- add preflight)

Add a `$preflighted` property and `runPreflight()` method. In `handle()`, call preflight before dispatching `ActionExecuting`:

```php
/**
 * @var array<class-string, true>
 */
private array $preflighted = [];

public function handle(StepContext $context, Closure $next): mixed
{
    $actionResults = [];

    foreach ($context->step->operations() as $action) {
        $action->withContext($context->recipeContext);

        if ($preflightFailure = $this->runPreflight($action, $context)) {
            $actionResults[] = $preflightFailure;

            $context->result = StepResult::failed(
                name: $context->step->name,
                actionResults: $actionResults,
            );

            return $context;
        }

        $context->dispatcher->dispatch(new ActionExecuting($action));

        // ... rest of existing handle() logic unchanged ...
    }

    // ... rest unchanged ...
}

/**
 * Run the preflight check for an action if it hasn't been run yet.
 *
 * Returns an ActionResult on failure, or null if the check passed or wasn't needed.
 */
private function runPreflight(\Compose\Actions\Action $action, StepContext $context): ?ActionResult
{
    $class = $action::class;

    if (isset($this->preflighted[$class])) {
        return null;
    }

    $preflight = $action->preflight();

    if ($preflight === null) {
        $this->preflighted[$class] = true;

        return null;
    }

    $result = $context->executor->execute(
        $preflight->toArray(),
        $context->recipeContext->workingDirectory,
    );

    $this->preflighted[$class] = true;

    if (! $result->successful) {
        return new ActionResult(
            command: $result->command,
            exitCode: $result->exitCode,
            output: $result->output,
            errorOutput: "Preflight check failed: {$preflight->toString()}",
            successful: false,
            duration: $result->duration,
            action: $action,
        );
    }

    return null;
}
```

### 8. `tests/Pest.php` (MODIFIED -- add `phpBinary` to context helper)

```php
function context(
    string $composerBinary = 'composer',
    string $phpBinary = 'php',                        // <-- ADD THIS
    string $gitBinary = 'git',
    Node $nodeManager = Node::Npm,
    ?string $workingDirectory = null,
): RecipeContext {
    return new RecipeContext(
        composerBinary: $composerBinary,
        phpBinary: $phpBinary,                        // <-- ADD THIS
        gitBinary: $gitBinary,
        nodeManager: $nodeManager,
        workingDirectory: $workingDirectory,
    );
}
```

### 9. `tests/Unit/ArtisanActionTest.php` (NEW)

```php
<?php

use Compose\Actions\Laravel\ArtisanAction;
use Compose\Enums\ArtisanOperation;

describe('ArtisanAction', function (): void {

    it('generates an artisan command', function (): void {
        $action = (new ArtisanAction('make:model Team'))->withContext(context());

        expect($action)
            ->toGenerateCommand('php artisan make:model Team')
            ->toBeOperation(ArtisanOperation::Run);
    });

    it('generates a command with flags', function (): void {
        $action = (new ArtisanAction('make:model Team -mf'))->withContext(context());

        expect($action)->toGenerateCommand('php artisan make:model Team -mf');
    });

    it('generates a command with long options', function (): void {
        $action = (new ArtisanAction('make:controller TeamController --api --model=Team'))->withContext(context());

        expect($action)->toGenerateCommand('php artisan make:controller TeamController --api --model=Team');
    });

    it('generates a single-word command', function (): void {
        $action = (new ArtisanAction('migrate'))->withContext(context());

        expect($action)->toGenerateCommand('php artisan migrate');
    });

    it('generates a vendor:publish command', function (): void {
        $action = (new ArtisanAction('vendor:publish --tag=cashier-migrations'))->withContext(context());

        expect($action)->toGenerateCommand('php artisan vendor:publish --tag=cashier-migrations');
    });

    it('uses a custom php binary from context', function (): void {
        $action = (new ArtisanAction('migrate'))->withContext(context(phpBinary: '/usr/bin/php8.3'));

        expect($action)->toGenerateCommand('/usr/bin/php8.3 artisan migrate');
    });

    it('cannot be rolled back', function (): void {
        $action = (new ArtisanAction('migrate'))->withContext(context());

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->rollback())->toBeNull();
    });

    it('returns the correct command array', function (): void {
        $action = (new ArtisanAction('make:model Team -mf'))->withContext(context());

        expect($action->command()->toArray())->toBe(['php', 'artisan', 'make:model', 'Team', '-mf']);
    });

    it('returns a preflight command', function (): void {
        $action = (new ArtisanAction('migrate'))->withContext(context());

        $preflight = $action->preflight();

        expect($preflight)->not->toBeNull();
        expect($preflight->toString())->toBe('php artisan --version');
    });

    it('uses the custom php binary in the preflight command', function (): void {
        $action = (new ArtisanAction('migrate'))->withContext(context(phpBinary: '/usr/bin/php8.3'));

        expect($action->preflight()->toString())->toBe('/usr/bin/php8.3 artisan --version');
    });

});
```

### 10. `tests/Unit/ArtisanBuilderTest.php` (NEW)

```php
<?php

use Compose\Actions\Laravel\ArtisanAction;
use Compose\Builders\ArtisanBuilder;

describe('ArtisanBuilder', function (): void {

    it('adds commands with run', function (): void {
        $builder = new ArtisanBuilder;

        $builder->run('make:model Team');

        $actions = $builder->actions();

        expect($actions)->toHaveCount(1);
        expect($actions[0])->toBeInstanceOf(ArtisanAction::class);
        expect($actions[0]->command)->toBe('make:model Team');
    });

    it('adds multiple commands with chained run calls', function (): void {
        $builder = new ArtisanBuilder;

        $builder
            ->run('make:controller TeamController --api')
            ->run('make:resource TeamResource');

        $actions = $builder->actions();

        expect($actions)->toHaveCount(2);
        expect($actions[0]->command)->toBe('make:controller TeamController --api');
        expect($actions[1]->command)->toBe('make:resource TeamResource');
    });

    it('generates a migrate command', function (): void {
        $builder = new ArtisanBuilder;

        $builder->migrate();

        $actions = $builder->actions();

        expect($actions)->toHaveCount(1);
        expect($actions[0]->command)->toBe('migrate');
    });

    it('generates a migrate:fresh command', function (): void {
        $builder = new ArtisanBuilder;

        $builder->migrate(fresh: true);

        expect($builder->actions()[0]->command)->toBe('migrate:fresh');
    });

    it('generates a migrate command with seed', function (): void {
        $builder = new ArtisanBuilder;

        $builder->migrate(seed: true);

        expect($builder->actions()[0]->command)->toBe('migrate --seed');
    });

    it('generates a migrate:fresh --seed command', function (): void {
        $builder = new ArtisanBuilder;

        $builder->migrate(fresh: true, seed: true);

        expect($builder->actions()[0]->command)->toBe('migrate:fresh --seed');
    });

    it('generates a db:seed command with no arguments', function (): void {
        $builder = new ArtisanBuilder;

        $builder->seed();

        $actions = $builder->actions();

        expect($actions)->toHaveCount(1);
        expect($actions[0]->command)->toBe('db:seed');
    });

    it('generates db:seed commands for each named seeder', function (): void {
        $builder = new ArtisanBuilder;

        $builder->seed('RolesSeeder', 'TeamSeeder');

        $actions = $builder->actions();

        expect($actions)->toHaveCount(2);
        expect($actions[0]->command)->toBe('db:seed --class=RolesSeeder');
        expect($actions[1]->command)->toBe('db:seed --class=TeamSeeder');
    });

    it('generates a vendor:publish command with provider', function (): void {
        $builder = new ArtisanBuilder;

        $builder->publish(provider: 'Spatie\Permission\PermissionServiceProvider');

        expect($builder->actions()[0]->command)
            ->toBe('vendor:publish --provider=Spatie\Permission\PermissionServiceProvider');
    });

    it('generates a vendor:publish command with tag', function (): void {
        $builder = new ArtisanBuilder;

        $builder->publish(tag: 'permission-migrations');

        expect($builder->actions()[0]->command)->toBe('vendor:publish --tag=permission-migrations');
    });

    it('generates a vendor:publish command with provider and tag', function (): void {
        $builder = new ArtisanBuilder;

        $builder->publish(
            provider: 'Spatie\Permission\PermissionServiceProvider',
            tag: 'permission-migrations',
        );

        expect($builder->actions()[0]->command)
            ->toBe('vendor:publish --provider=Spatie\Permission\PermissionServiceProvider --tag=permission-migrations');
    });

    it('generates a generic make command', function (): void {
        $builder = new ArtisanBuilder;

        $builder->make(resource: 'controller', name: 'TeamController --api');

        expect($builder->actions()[0]->command)->toBe('make:controller TeamController --api');
    });

    it('generates a makeModel command', function (): void {
        $builder = new ArtisanBuilder;

        $builder->makeModel(name: 'Team');

        expect($builder->actions()[0]->command)->toBe('make:model Team');
    });

    it('generates a makeModel command with all flags', function (): void {
        $builder = new ArtisanBuilder;

        $builder->makeModel(name: 'Team', migration: true, factory: true, seeder: true);

        expect($builder->actions()[0]->command)->toBe('make:model Team -m -f -s');
    });

    it('generates a makeModel command with partial flags', function (): void {
        $builder = new ArtisanBuilder;

        $builder->makeModel(name: 'Team', migration: true);

        expect($builder->actions()[0]->command)->toBe('make:model Team -m');
    });

    it('applies when condition when true', function (): void {
        $builder = new ArtisanBuilder;

        $builder
            ->run('migrate')
            ->when(true, fn (ArtisanBuilder $b) => $b->run('db:seed'));

        expect($builder->actions())->toHaveCount(2);
    });

    it('skips when condition when false', function (): void {
        $builder = new ArtisanBuilder;

        $builder
            ->run('migrate')
            ->when(false, fn (ArtisanBuilder $b) => $b->run('db:seed'));

        expect($builder->actions())->toHaveCount(1);
    });

    it('supports closure-based when conditions', function (): void {
        $builder = new ArtisanBuilder;

        $builder->when(fn (): true => true, fn (ArtisanBuilder $b) => $b->run('migrate'));

        expect($builder->actions())->toHaveCount(1);
    });

    it('returns an empty array when no commands are added', function (): void {
        $builder = new ArtisanBuilder;

        expect($builder->actions())->toBe([]);
    });

});
```

### 11. `tests/Unit/StepTest.php` (MODIFIED -- add these tests)

Add imports:

```php
use Compose\Actions\Laravel\ArtisanAction;
use Compose\Builders\ArtisanBuilder;
```

Add these tests inside the `describe('Step', ...)` block:

```php
    it('adds a single artisan action from a string', function (): void {
        $step = new Step(
            context: context(),
            name: 'Test step',
        );

        $step->artisan('make:model Team -mf');

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(ArtisanAction::class);
        expect($operations[0]->command)->toBe('make:model Team -mf');
    });

    it('adds multiple artisan actions from a closure', function (): void {
        $step = new Step(
            context: context(),
            name: 'Test step',
        );

        $step->artisan(fn (ArtisanBuilder $a) => $a
            ->run('make:controller TeamController --api')
            ->run('make:resource TeamResource')
        );

        $operations = $step->operations();

        expect($operations)->toHaveCount(2);
        expect($operations[0])->toBeInstanceOf(ArtisanAction::class);
        expect($operations[0]->command)->toBe('make:controller TeamController --api');
        expect($operations[1]->command)->toBe('make:resource TeamResource');
    });

    it('chains artisan with other methods', function (): void {
        $step = new Step(
            context: context(),
            name: 'Test step',
            callback: function (Step $step): void {
                $step
                    ->composer(install: ['laravel/framework'])
                    ->artisan('migrate')
                    ->commit('Setup');
            },
        );

        $step->resolveOperations();

        $operations = $step->operations();

        expect($operations)->toHaveCount(4);
        expect($operations[1])->toBeInstanceOf(ArtisanAction::class);
        expect($operations[2])->toBeInstanceOf(GitAdd::class);
        expect($operations[3])->toBeInstanceOf(GitCommit::class);
    });
```

### 12. `tests/Unit/RunnerTest.php` (MODIFIED -- add preflight tests)

Add import:

```php
use Compose\Builders\ArtisanBuilder;
```

Add this `describe` block (before `describe('Runner fresh guard', ...)`):

```php
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
            $step->artisan(fn (ArtisanBuilder $a) => $a
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
```

---

## CRITICAL BUG TO FIX

The "Runner fresh guard" tests in `tests/Unit/RunnerTest.php` are destructive. The test `it throws when fresh mode targets getcwd()` calls `->in((string) getcwd(), fresh: true)` which resolves to the project directory. The Runner's fresh-mode deletion runs **before** the `DangerousPathException` guard catches it, deleting the project files. This must be fixed before running tests again:

- The guard check must run **before** any directory deletion in the Runner
- Or the fresh guard tests must use a safe temp directory that they assert throws, not the actual project root