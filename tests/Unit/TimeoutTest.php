<?php

use Compose\Actions\Artisan\ArtisanAction;
use Compose\Actions\Composer\ComposerInstall;
use Compose\Actions\Composer\ComposerRemove;
use Compose\Actions\Composer\ComposerRun;
use Compose\Actions\Git\GitAdd;
use Compose\Actions\Git\GitCheckout;
use Compose\Actions\Git\GitClone;
use Compose\Actions\Git\GitCommit;
use Compose\Actions\Git\GitInit;
use Compose\Actions\Node\NodeInstall;
use Compose\Actions\Node\NodeRemove;
use Compose\Actions\Node\NodeRun;
use Compose\Actions\Quality\PintFormat;
use Compose\Actions\Quality\RectorProcess;
use Compose\Actions\Sink;
use Compose\Actions\Test\TestAction;
use Compose\Execution\ProcessExecutor;
use Compose\Step;

// -------------------------------------------------------------------
// Smart Defaults
// -------------------------------------------------------------------

describe('smart defaults', function (): void {

    it('returns correct timeout for artisan make commands', function (): void {
        expect((new ArtisanAction('make:model Team'))->defaultTimeout())->toBe(15.0);
        expect((new ArtisanAction('make:controller TeamController --api'))->defaultTimeout())->toBe(15.0);
        expect((new ArtisanAction('make:migration create_teams_table'))->defaultTimeout())->toBe(15.0);
    });

    it('returns correct timeout for artisan migrate commands', function (): void {
        expect((new ArtisanAction('migrate'))->defaultTimeout())->toBe(120.0);
        expect((new ArtisanAction('migrate:fresh --seed'))->defaultTimeout())->toBe(120.0);
        expect((new ArtisanAction('migrate:rollback'))->defaultTimeout())->toBe(120.0);
    });

    it('returns correct timeout for artisan db:seed commands', function (): void {
        expect((new ArtisanAction('db:seed'))->defaultTimeout())->toBe(120.0);
        expect((new ArtisanAction('db:seed --class=UserSeeder'))->defaultTimeout())->toBe(120.0);
    });

    it('returns correct timeout for vendor:publish', function (): void {
        expect((new ArtisanAction('vendor:publish --tag=config'))->defaultTimeout())->toBe(30.0);
    });

    it('returns correct timeout for other artisan commands', function (): void {
        expect((new ArtisanAction('optimize'))->defaultTimeout())->toBe(60.0);
        expect((new ArtisanAction('route:cache'))->defaultTimeout())->toBe(60.0);
    });

    it('returns correct timeout for composer actions', function (): void {
        expect((new ComposerInstall(['laravel/framework']))->defaultTimeout())->toBe(300.0);
        expect((new ComposerRemove(['laravel/framework']))->defaultTimeout())->toBe(120.0);
        expect((new ComposerRun(script: 'test'))->defaultTimeout())->toBe(120.0);
    });

    it('returns correct timeout for node actions', function (): void {
        expect((new NodeInstall(['vue']))->defaultTimeout())->toBe(300.0);
        expect((new NodeRemove(['vue']))->defaultTimeout())->toBe(60.0);
        expect((new NodeRun(script: 'build'))->defaultTimeout())->toBe(120.0);
    });

    it('returns correct timeout for git actions', function (): void {
        expect((new GitClone(repo: 'https://github.com/laravel/laravel.git'))->defaultTimeout())->toBe(300.0);
        expect((new GitInit)->defaultTimeout())->toBe(15.0);
        expect((new GitAdd)->defaultTimeout())->toBe(30.0);
        expect((new GitCommit(message: 'init'))->defaultTimeout())->toBe(30.0);
        expect((new GitCheckout(branch: 'main'))->defaultTimeout())->toBe(15.0);
    });

    it('returns correct timeout for sink action', function (): void {
        expect((new Sink(from: 'https://example.com/file.yml'))->defaultTimeout())->toBe(60.0);
    });

    it('returns correct timeout for quality actions', function (): void {
        expect((new PintFormat)->defaultTimeout())->toBe(120.0);
        expect((new RectorProcess)->defaultTimeout())->toBe(120.0);
    });

    it('returns correct timeout for test action', function (): void {
        expect((new TestAction(path: 'tests/Feature/TeamTest.php'))->defaultTimeout())->toBe(300.0);
    });

    it('sets a short timeout on artisan preflight', function (): void {
        $action = (new ArtisanAction('migrate'))->withContext(context());

        expect($action->preflight()->getTimeout())->toBe(10.0);
    });

    it('sets a short timeout on test action preflight', function (): void {
        $action = (new TestAction(path: 'tests/Unit/Test.php'))->withContext(context());

        expect($action->preflight()->getTimeout())->toBe(10.0);
    });

});

// -------------------------------------------------------------------
// Compose-Level Timeout
// -------------------------------------------------------------------

describe('compose-level timeout', function (): void {

    it('defaults to null when not set', function (): void {
        $config = compose('Test')->toConfig();

        expect($config->timeout)->toBeNull();
    });

    it('stores the timeout in recipe config', function (): void {
        $config = compose('Test')->timeout(30)->toConfig();

        expect($config->timeout)->toBe(30.0);
    });

    it('preserves timeout through withOverrides', function (): void {
        $config = compose('Test')->timeout(45)->toConfig();

        $overridden = $config->withOverrides(autoCommit: true);

        expect($overridden->timeout)->toBe(45.0);
    });

    it('can override timeout through withOverrides', function (): void {
        $config = compose('Test')->timeout(45)->toConfig();

        $overridden = $config->withOverrides(timeout: 90);

        expect($overridden->timeout)->toBe(90.0);
    });

});

// -------------------------------------------------------------------
// Step-Level Timeout
// -------------------------------------------------------------------

describe('step-level timeout', function (): void {

    it('defaults to null when not set', function (): void {
        $step = new Step(name: 'Test');

        expect($step->timeout)->toBeNull();
    });

    it('stores a timeout on the step', function (): void {
        $step = new Step(name: 'Test', timeout: 60.0);

        expect($step->timeout)->toBe(60.0);
    });

    it('accepts timeout via compose step method', function (): void {
        $config = compose('Test')
            ->step('Fast step', fn (Step $step) => $step->artisan('make:model Team'), timeout: 15)
            ->toConfig();

        expect($config->steps[0]->timeout)->toBe(15.0);
    });

});

// -------------------------------------------------------------------
// Timeout Resolution Cascade
// -------------------------------------------------------------------

describe('timeout resolution cascade', function (): void {

    afterEach(function (): void {
        ProcessExecutor::reset();
    });

    it('uses the action smart default when no overrides are set', function (): void {
        $fake = ProcessExecutor::fake();

        $recipe = compose('Test');
        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['laravel/framework']));
        $recipe->run();

        $fake->assertExecutedWithTimeout(['composer', 'require', 'laravel/framework'], 300.0);
    });

    it('compose timeout overrides the action smart default', function (): void {
        $fake = ProcessExecutor::fake();

        $recipe = compose('Test')->timeout(45);
        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['laravel/framework']));
        $recipe->run();

        $fake->assertExecutedWithTimeout(['composer', 'require', 'laravel/framework'], 45.0);
    });

    it('step timeout overrides the compose timeout', function (): void {
        $fake = ProcessExecutor::fake();

        $recipe = compose('Test')->timeout(45);
        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['laravel/framework']), timeout: 90);
        $recipe->run();

        $fake->assertExecutedWithTimeout(['composer', 'require', 'laravel/framework'], 90.0);
    });

    it('step timeout overrides action smart default', function (): void {
        $fake = ProcessExecutor::fake();

        $recipe = compose('Test');
        $recipe->step('Artisan', fn (Step $step) => $step->artisan('make:model Team'), timeout: 5);
        $recipe->run();

        $fake->assertExecutedWithTimeout(['php', 'artisan', 'make:model', 'Team'], 5.0);
    });

    it('uses ProcessExecutor default when no timeout is configured and action has no smart default', function (): void {
        $fake = ProcessExecutor::fake();

        $recipe = compose('Test');
        $recipe->step('Git', fn (Step $step) => $step->addOperation(new GitAdd));
        $recipe->run();

        $fake->assertExecutedWithTimeout(['git', 'add', '-A'], 30.0);
    });

    it('applies different timeouts per step', function (): void {
        $fake = ProcessExecutor::fake();

        $recipe = compose('Test');
        $recipe->step('Fast', fn (Step $step) => $step->artisan('make:model Team'), timeout: 10);
        $recipe->step('Slow', fn (Step $step) => $step->artisan('migrate'), timeout: 180);
        $recipe->run();

        $fake->assertExecutedWithTimeout(['php', 'artisan', 'make:model', 'Team'], 10.0);
        $fake->assertExecutedWithTimeout(['php', 'artisan', 'migrate'], 180.0);
    });

});
