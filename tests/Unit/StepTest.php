<?php

use Compose\Actions\Artisan\ArtisanAction;
use Compose\Actions\Git\GitAdd;
use Compose\Actions\Git\GitCommit;
use Compose\Builders\Artisan;
use Compose\Step;

describe('Step', function (): void {

    it('adds git add and git commit operations when commit is called with a message', function (): void {
        $step = new Step(name: 'Test step');

        $step->commit('Initial commit');

        $operations = $step->operations();

        expect($operations)->toHaveCount(2);
        expect($operations[0])->toBeInstanceOf(GitAdd::class);
        expect($operations[1])->toBeInstanceOf(GitCommit::class);
        expect($operations[1]->message)->toBe('Initial commit');
    });

    it('adds git add and git commit with null message when commit is called without arguments', function (): void {
        $step = new Step(name: 'Test step');

        $step->commit();

        $operations = $step->operations();

        expect($operations)->toHaveCount(2);
        expect($operations[0])->toBeInstanceOf(GitAdd::class);
        expect($operations[1])->toBeInstanceOf(GitCommit::class);
        expect($operations[1]->message)->toBeNull();
    });

    it('appends commit operations after existing operations', function (): void {
        $step = new Step(
            name: 'Test step',
            callback: function (Step $step): void {
                $step->composer(install: ['laravel/framework']);
                $step->commit('After install');
            },
        );

        $step->resolveOperations();

        $operations = $step->operations();

        expect($operations)->toHaveCount(3);
        expect($operations[0])->not->toBeInstanceOf(GitAdd::class);
        expect($operations[1])->toBeInstanceOf(GitAdd::class);
        expect($operations[2])->toBeInstanceOf(GitCommit::class);
    });

    it('supports chaining commit with other fluent methods', function (): void {
        $step = new Step(
            name: 'Test step',
            callback: function (Step $step): void {
                $step
                    ->composer(install: ['laravel/framework'])
                    ->commit('Install laravel')
                    ->composer(dev: ['pestphp/pest']);
            },
        );

        $step->resolveOperations();

        $operations = $step->operations();

        expect($operations)->toHaveCount(4);
        expect($operations[1])->toBeInstanceOf(GitAdd::class);
        expect($operations[2])->toBeInstanceOf(GitCommit::class);
        expect($operations[2]->message)->toBe('Install laravel');
    });

    it('adds a single artisan action from a string', function (): void {
        $step = new Step(name: 'Test step');

        $step->artisan('make:model Team -mf');

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(ArtisanAction::class);
        expect($operations[0]->command)->toBe('make:model Team -mf');
    });

    it('adds multiple artisan actions from a closure', function (): void {
        $step = new Step(name: 'Test step');

        $step->artisan(fn (Artisan $a) => $a
            ->run('make:controller TeamController --api')
            ->run('make:resource TeamResource')
        );

        $operations = $step->operations();

        expect($operations)->toHaveCount(2);
        expect($operations[0])->toBeInstanceOf(ArtisanAction::class);
        expect($operations[0]->command)->toBe('make:controller TeamController --api');
        expect($operations[1]->command)->toBe('make:resource TeamResource');
    });

    it('can be constructed without a context', function (): void {
        $step = new Step(name: 'Test step');

        expect($step->name)->toBe('Test step');
        expect($step->operations())->toBeEmpty();
    });

    it('chains artisan with other methods', function (): void {
        $step = new Step(
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

});
