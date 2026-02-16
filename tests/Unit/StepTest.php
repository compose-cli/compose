<?php

use Compose\Actions\Git\GitAdd;
use Compose\Actions\Git\GitCommit;
use Compose\Step;

describe('Step', function (): void {

    it('adds git add and git commit operations when commit is called with a message', function (): void {
        $step = new Step(
            context: context(),
            name: 'Test step',
        );

        $step->commit('Initial commit');

        $operations = $step->operations();

        expect($operations)->toHaveCount(2);
        expect($operations[0])->toBeInstanceOf(GitAdd::class);
        expect($operations[1])->toBeInstanceOf(GitCommit::class);
        expect($operations[1]->message)->toBe('Initial commit');
    });

    it('adds git add and git commit with null message when commit is called without arguments', function (): void {
        $step = new Step(
            context: context(),
            name: 'Test step',
        );

        $step->commit();

        $operations = $step->operations();

        expect($operations)->toHaveCount(2);
        expect($operations[0])->toBeInstanceOf(GitAdd::class);
        expect($operations[1])->toBeInstanceOf(GitCommit::class);
        expect($operations[1]->message)->toBeNull();
    });

    it('appends commit operations after existing operations', function (): void {
        $step = new Step(
            context: context(),
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
            context: context(),
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

});
