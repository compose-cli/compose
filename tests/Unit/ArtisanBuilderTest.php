<?php

use Compose\Actions\ArtisanAction;
use Compose\Builders\Artisan;

describe('Artisan', function (): void {

    it('adds commands with run', function (): void {
        $builder = new Artisan;

        $builder->run('make:model Team');

        $actions = $builder->actions();

        expect($actions)->toHaveCount(1);
        expect($actions[0])->toBeInstanceOf(ArtisanAction::class);
        expect($actions[0]->command)->toBe('make:model Team');
    });

    it('adds multiple commands with chained run calls', function (): void {
        $builder = new Artisan;

        $builder
            ->run('make:controller TeamController --api')
            ->run('make:resource TeamResource');

        $actions = $builder->actions();

        expect($actions)->toHaveCount(2);
        expect($actions[0]->command)->toBe('make:controller TeamController --api');
        expect($actions[1]->command)->toBe('make:resource TeamResource');
    });

    it('generates a migrate command', function (): void {
        $builder = new Artisan;

        $builder->migrate();

        $actions = $builder->actions();

        expect($actions)->toHaveCount(1);
        expect($actions[0]->command)->toBe('migrate');
    });

    it('generates a migrate:fresh command', function (): void {
        $builder = new Artisan;

        $builder->migrate(fresh: true);

        expect($builder->actions()[0]->command)->toBe('migrate:fresh');
    });

    it('generates a migrate command with seed', function (): void {
        $builder = new Artisan;

        $builder->migrate(seed: true);

        expect($builder->actions()[0]->command)->toBe('migrate --seed');
    });

    it('generates a migrate:fresh --seed command', function (): void {
        $builder = new Artisan;

        $builder->migrate(fresh: true, seed: true);

        expect($builder->actions()[0]->command)->toBe('migrate:fresh --seed');
    });

    it('generates a db:seed command with no arguments', function (): void {
        $builder = new Artisan;

        $builder->seed();

        $actions = $builder->actions();

        expect($actions)->toHaveCount(1);
        expect($actions[0]->command)->toBe('db:seed');
    });

    it('generates db:seed commands for each named seeder', function (): void {
        $builder = new Artisan;

        $builder->seed('RolesSeeder', 'TeamSeeder');

        $actions = $builder->actions();

        expect($actions)->toHaveCount(2);
        expect($actions[0]->command)->toBe('db:seed --class=RolesSeeder');
        expect($actions[1]->command)->toBe('db:seed --class=TeamSeeder');
    });

    it('generates a vendor:publish command with provider', function (): void {
        $builder = new Artisan;

        $builder->publish(provider: 'Spatie\Permission\PermissionServiceProvider');

        expect($builder->actions()[0]->command)
            ->toBe('vendor:publish --provider=Spatie\Permission\PermissionServiceProvider');
    });

    it('generates a vendor:publish command with tag', function (): void {
        $builder = new Artisan;

        $builder->publish(tag: 'permission-migrations');

        expect($builder->actions()[0]->command)->toBe('vendor:publish --tag=permission-migrations');
    });

    it('generates a vendor:publish command with provider and tag', function (): void {
        $builder = new Artisan;

        $builder->publish(
            provider: 'Spatie\Permission\PermissionServiceProvider',
            tag: 'permission-migrations',
        );

        expect($builder->actions()[0]->command)
            ->toBe('vendor:publish --provider=Spatie\Permission\PermissionServiceProvider --tag=permission-migrations');
    });

    it('generates a generic make command', function (): void {
        $builder = new Artisan;

        $builder->make(resource: 'controller', name: 'TeamController --api');

        expect($builder->actions()[0]->command)->toBe('make:controller TeamController --api');
    });

    it('generates a makeModel command', function (): void {
        $builder = new Artisan;

        $builder->makeModel(name: 'Team');

        expect($builder->actions()[0]->command)->toBe('make:model Team');
    });

    it('generates a makeModel command with all flags', function (): void {
        $builder = new Artisan;

        $builder->makeModel(name: 'Team', migration: true, factory: true, seeder: true);

        expect($builder->actions()[0]->command)->toBe('make:model Team -m -f -s');
    });

    it('generates a makeModel command with partial flags', function (): void {
        $builder = new Artisan;

        $builder->makeModel(name: 'Team', migration: true);

        expect($builder->actions()[0]->command)->toBe('make:model Team -m');
    });

    it('applies when condition when true', function (): void {
        $builder = new Artisan;

        $builder
            ->run('migrate')
            ->when(true, fn (Artisan $b) => $b->run('db:seed'));

        expect($builder->actions())->toHaveCount(2);
    });

    it('skips when condition when false', function (): void {
        $builder = new Artisan;

        $builder
            ->run('migrate')
            ->when(false, fn (Artisan $b) => $b->run('db:seed'));

        expect($builder->actions())->toHaveCount(1);
    });

    it('supports closure-based when conditions', function (): void {
        $builder = new Artisan;

        $builder->when(fn (): true => true, fn (Artisan $b) => $b->run('migrate'));

        expect($builder->actions())->toHaveCount(1);
    });

    it('returns an empty array when no commands are added', function (): void {
        $builder = new Artisan;

        expect($builder->actions())->toBe([]);
    });

});