<?php

use Compose\Exceptions\CircularDependencyException;
use Compose\Execution\ProcessExecutor;
use Compose\Recipe;
use Compose\Step;

// -------------------------------------------------------------------------
// Test Fixtures
// -------------------------------------------------------------------------

class InstallPermissions extends Recipe
{
    #[Override]
    public function compose(Step $step): void
    {
        $step->composer(install: ['spatie/laravel-permission']);
    }
}

class InstallTelescope extends Recipe
{
    #[Override]
    public function compose(Step $step): void
    {
        $step->composer(dev: ['laravel/telescope']);
    }
}

class CustomNameRecipe extends Recipe
{
    #[Override]
    public function name(): string
    {
        return 'My Custom Recipe';
    }

    #[Override]
    public function description(): string
    {
        return 'A recipe with custom name and description.';
    }

    #[Override]
    public function compose(Step $step): void
    {
        $step->composer(install: ['custom/package']);
    }
}

class RecipeWithHooks extends Recipe
{
    /** @var string[] */
    public array $callOrder = [];

    #[Override]
    public function before(Step $step): void
    {
        $this->callOrder[] = 'before';
        $step->composer(install: ['before/package']);
    }

    #[Override]
    public function compose(Step $step): void
    {
        $this->callOrder[] = 'compose';
        $step->composer(install: ['main/package']);
    }

    #[Override]
    public function after(Step $step): void
    {
        $this->callOrder[] = 'after';
        $step->composer(install: ['after/package']);
    }
}

class ConfigurableRecipe extends Recipe
{
    /** @var string[] */
    private readonly array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = $roles;
    }

    public static function withRoles(string ...$roles): static
    {
        return new static(...$roles);
    }

    #[Override]
    public function compose(Step $step): void
    {
        $step->composer(install: ['spatie/laravel-permission']);

        foreach ($this->roles as $role) {
            $step->artisan("permission:create-role {$role}");
        }
    }
}

class RecipeWithDependency extends Recipe
{
    /** @return array<class-string<Recipe>> */
    #[Override]
    public function requires(): array
    {
        return [InstallPermissions::class];
    }

    #[Override]
    public function compose(Step $step): void
    {
        $step->artisan('permission:create-role admin');
    }
}

class RecipeWithTransitiveDeps extends Recipe
{
    /** @return array<class-string<Recipe>> */
    #[Override]
    public function requires(): array
    {
        return [RecipeWithDependency::class];
    }

    #[Override]
    public function compose(Step $step): void
    {
        $step->artisan('permission:create-role editor');
    }
}

class CircularA extends Recipe
{
    /** @return array<class-string<Recipe>> */
    #[Override]
    public function requires(): array
    {
        return [CircularB::class];
    }

    #[Override]
    public function compose(Step $step): void {}
}

class CircularB extends Recipe
{
    /** @return array<class-string<Recipe>> */
    #[Override]
    public function requires(): array
    {
        return [CircularA::class];
    }

    #[Override]
    public function compose(Step $step): void {}
}

// -------------------------------------------------------------------------
// Tests
// -------------------------------------------------------------------------

describe('Recipe', function (): void {

    afterEach(function (): void {
        ProcessExecutor::reset();
    });

    it('executes a basic recipe', function (): void {
        ProcessExecutor::fake();

        $result = compose('Test')
            ->recipe(new InstallPermissions)
            ->run();

        expect($result->successful)->toBeTrue();
        expect($result->stepsCompleted)->toBe(1);

        ProcessExecutor::assertExecuted(['composer', 'require', 'spatie/laravel-permission']);
    });

    it('defaults recipe name to short class name', function (): void {
        $config = compose('Test')
            ->recipe(new InstallPermissions)
            ->toConfig();

        expect($config->steps)->toHaveCount(1);
        expect($config->steps[0]->name)->toBe('InstallPermissions');
    });

    it('uses custom name and description from recipe', function (): void {
        $config = compose('Test')
            ->recipe(new CustomNameRecipe)
            ->toConfig();

        expect($config->steps[0]->name)->toBe('My Custom Recipe');
        expect($config->steps[0]->description)->toBe('A recipe with custom name and description.');
    });

    it('passes config through static factory', function (): void {
        ProcessExecutor::fake();

        $result = compose('Test')
            ->recipe(ConfigurableRecipe::withRoles('admin', 'editor'))
            ->run();

        expect($result->successful)->toBeTrue();

        ProcessExecutor::assertExecuted(['composer', 'require', 'spatie/laravel-permission']);
        ProcessExecutor::assertExecuted(['php', 'artisan', 'permission:create-role', 'admin']);
        ProcessExecutor::assertExecuted(['php', 'artisan', 'permission:create-role', 'editor']);
    });

    it('instantiates class string recipes', function (): void {
        ProcessExecutor::fake();

        $result = compose('Test')
            ->recipe(InstallPermissions::class)
            ->run();

        expect($result->successful)->toBeTrue();

        ProcessExecutor::assertExecuted(['composer', 'require', 'spatie/laravel-permission']);
    });

    it('creates steps from an array of recipes', function (): void {
        ProcessExecutor::fake();

        $result = compose('Test')
            ->recipe([InstallPermissions::class, InstallTelescope::class])
            ->run();

        expect($result->successful)->toBeTrue();
        expect($result->stepsCompleted)->toBe(2);

        ProcessExecutor::assertExecuted(['composer', 'require', 'spatie/laravel-permission']);
        ProcessExecutor::assertExecuted(['composer', 'require', '--dev', 'laravel/telescope']);
    });

    it('interleaves recipes and inline steps in declaration order', function (): void {
        $fake = ProcessExecutor::fake();

        compose('Test')
            ->step('Inline First', fn (Step $step) => $step->composer(install: ['pkg-a']))
            ->recipe(new InstallPermissions)
            ->step('Inline Last', fn (Step $step) => $step->composer(install: ['pkg-b']))
            ->run();

        $executed = $fake->executed();
        $commands = array_map(fn (array $cmd) => implode(' ', $cmd['command']), $executed);

        $idxA = array_search('composer require pkg-a', $commands);
        $idxPerm = array_search('composer require spatie/laravel-permission', $commands);
        $idxB = array_search('composer require pkg-b', $commands);

        expect($idxA)->toBeLessThan($idxPerm);
        expect($idxPerm)->toBeLessThan($idxB);
    });

    it('calls before, compose, and after hooks in order', function (): void {
        ProcessExecutor::fake();

        $recipe = new RecipeWithHooks;

        compose('Test')
            ->recipe($recipe)
            ->run();

        expect($recipe->callOrder)->toBe(['before', 'compose', 'after']);

        ProcessExecutor::assertExecuted(['composer', 'require', 'before/package']);
        ProcessExecutor::assertExecuted(['composer', 'require', 'main/package']);
        ProcessExecutor::assertExecuted(['composer', 'require', 'after/package']);
    });

    it('works with plan()', function (): void {
        $plan = compose('Test')
            ->recipe(new InstallPermissions)
            ->plan();

        expect($plan->steps)->toHaveCount(1);
        expect($plan->steps[0]->name)->toBe('InstallPermissions');
        expect($plan->steps[0]->commands)->not->toBeEmpty();
    });

    it('deduplicates the same recipe class registered multiple times', function (): void {
        ProcessExecutor::fake();

        $result = compose('Test')
            ->recipe(InstallPermissions::class)
            ->recipe(new InstallPermissions)
            ->run();

        expect($result->successful)->toBeTrue();
        expect($result->stepsCompleted)->toBe(1);
    });

});

describe('Recipe dependencies', function (): void {

    afterEach(function (): void {
        ProcessExecutor::reset();
    });

    it('auto-resolves a missing dependency before the dependent recipe', function (): void {
        $fake = ProcessExecutor::fake();

        $result = compose('Test')
            ->recipe(RecipeWithDependency::class)
            ->run();

        expect($result->successful)->toBeTrue();
        expect($result->stepsCompleted)->toBe(2);

        $executed = $fake->executed();
        $commands = array_map(fn (array $cmd) => implode(' ', $cmd['command']), $executed);

        $idxPermission = array_search('composer require spatie/laravel-permission', $commands);
        $idxRole = array_search('php artisan permission:create-role admin', $commands);

        expect($idxPermission)->not->toBeFalse();
        expect($idxRole)->not->toBeFalse();
        expect($idxPermission)->toBeLessThan($idxRole);
    });

    it('skips already-registered dependency', function (): void {
        ProcessExecutor::fake();

        $result = compose('Test')
            ->recipe(InstallPermissions::class)
            ->recipe(RecipeWithDependency::class)
            ->run();

        expect($result->successful)->toBeTrue();
        expect($result->stepsCompleted)->toBe(2);
    });

    it('resolves transitive dependencies in correct order', function (): void {
        $fake = ProcessExecutor::fake();

        $result = compose('Test')
            ->recipe(RecipeWithTransitiveDeps::class)
            ->run();

        expect($result->successful)->toBeTrue();
        expect($result->stepsCompleted)->toBe(3);

        $config = compose('Test')
            ->recipe(RecipeWithTransitiveDeps::class)
            ->toConfig();

        expect($config->steps[0]->name)->toBe('InstallPermissions');
        expect($config->steps[1]->name)->toBe('RecipeWithDependency');
        expect($config->steps[2]->name)->toBe('RecipeWithTransitiveDeps');
    });

    it('throws on circular dependencies', function (): void {
        compose('Test')->recipe(CircularA::class);
    })->throws(CircularDependencyException::class);

    it('auto-resolved recipes run before dependents', function (): void {
        $fake = ProcessExecutor::fake();

        compose('Test')
            ->step('Setup', fn (Step $step) => $step->composer(install: ['pkg-a']))
            ->recipe(RecipeWithDependency::class)
            ->run();

        $executed = $fake->executed();
        $commands = array_map(fn (array $cmd) => implode(' ', $cmd['command']), $executed);

        $idxSetup = array_search('composer require pkg-a', $commands);
        $idxPermission = array_search('composer require spatie/laravel-permission', $commands);
        $idxRole = array_search('php artisan permission:create-role admin', $commands);

        expect($idxSetup)->toBeLessThan($idxPermission);
        expect($idxPermission)->toBeLessThan($idxRole);
    });

});
