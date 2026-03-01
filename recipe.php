<?php

use Compose\Builders\Artisan;
use Compose\Builders\ConfigBuilder;
use Compose\Builders\EnvBuilder;
use Compose\Builders\ModifyBuilder;
use Compose\Enums\Node;
use Compose\Enums\TaskType;
use Compose\Step;

$recipe = compose('Compose CLI', type: TaskType::NewProject)
    ->in('tests/tmp', fresh: true)
    ->base(repo: 'https://github.com/laravel/laravel.git')
    ->node(Node::Yarn)
    ->commit(automatically: true, smart: true);

$recipe->step('Dev Tooling', function (Step $step): void {
    $step
        ->composer(
            dev: [
                'rector/rector',
                'laravel/pint',
                'pestphp/pest',
                'phpstan/phpstan',
            ],
        )
        ->node(dev: ['vite', '@vitejs/plugin-vue', 'tailwindcss']);
});

$recipe->step('Auth & Permissions', function (Step $step): void {
    $step
        ->composer(install: ['laravel/fortify', 'spatie/laravel-permission'])
        ->artisan(function (Artisan $a): void {
            $a->publish(provider: 'Laravel\Fortify\FortifyServiceProvider')
              ->publish(provider: 'Spatie\Permission\PermissionServiceProvider')
              ->config('permission.teams', true)
              ->config('permission', fn (ConfigBuilder $c) => $c
                  ->merge('guard_names', ['web', 'api'])
                  ->set('models.role', 'App\\Models\\Role')
                  ->set('models.permission', 'App\\Models\\Permission'))
              ->migrate()
              ->seed('RolesAndPermissionsSeeder');
        })
        ->modify('app/Models/User.php', fn (ModifyBuilder $m) => $m
            ->addTrait('Spatie\Permission\Traits\HasRoles')
            ->addTrait('Laravel\Fortify\TwoFactorAuthenticatable')
            ->addToArray('fillable', ['team_id', 'avatar'])
            ->addMethod('isAdmin', 'return $this->hasRole("admin");', returnType: 'bool'),
        );
});

$recipe->step('Environment', function (Step $step): void {
    $step
        ->env(function (EnvBuilder $env): void {
            $env->set('APP_NAME', 'Compose CLI')
                ->set('CACHE_DRIVER', 'redis')
                ->set('QUEUE_CONNECTION', 'redis')
                ->after('DB_PASSWORD')->section('# Redis', [
                    'REDIS_HOST' => '127.0.0.1',
                    'REDIS_PORT' => '6379',
                ])
                ->when('APP_DEBUG', 'true', fn (EnvBuilder $e) => $e
                    ->set('TELESCOPE_ENABLED', 'true'),
                );
        })
        ->env(['DB_DATABASE' => ':memory:'], path: '.env.testing');
});

$recipe->step('CI & Config', function (Step $step): void {
    $step
        ->sink(
            from: 'github:laravel/laravel@11.x:.github/workflows/tests.yml',
            to: '.github/workflows/tests.yml',
        )
        ->sink(from: 'github:laravel/laravel@11.x:phpunit.xml.dist')
        ->create('.github/workflows/deploy.yml', <<<'YAML'
            name: Deploy
            on:
              push:
                branches: [main]
            jobs:
              deploy:
                runs-on: ubuntu-latest
                steps:
                  - uses: actions/checkout@v4
                  - run: echo "Deploying..."
            YAML)
        ->modify('package.json', fn (ModifyBuilder $m) => $m
            ->json(fn (\Compose\Builders\JsonModifyBuilder $j) => $j
                ->set('scripts.lint', 'pint && rector process')
                ->set('scripts.test', 'pest')
                ->merge('keywords', ['laravel', 'compose'])),
        );
});

$recipe->step('Frontend', function (Step $step): void {
    $step
        ->node(install: ['vue', '@inertiajs/vue3'])
        ->node(run: 'build', allowFailure: true);
});

return $recipe;
