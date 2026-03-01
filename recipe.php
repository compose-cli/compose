<?php

use Compose\Enums\TaskType;
use Compose\Step;

$composer = compose('Compose CLI', type: TaskType::NewProject)
    ->in('tests/tmp', fresh: true)
    ->base(repo: 'https://github.com/laravel/laravel.git')
    ->commit(automatically: true, smart: true);

// Install Dev Dependencies
$composer->step('Install Dev Dependencies', function (Step $step): void {
    $step
        ->composer(run: 'setup')
        ->composer(
            dev: [
                'rector/rector',
                'laravel/pint',
                'pestphp/pest',
                'phpstan/phpstan',
            ]
        );
});

// setup authentication
$composer->step('Setup authentication', function (Step $step): void {
    $step
        ->composer(
            install: [
                'laravel/fortify',
            ],
            run: 'vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"'
        )
        ->artisan('migrate');
});

$composer->step('CI & Config', function (Step $step): void {
    $step
        ->sink(
            from: 'github:laravel/laravel@11.x:.github/workflows/tests.yml',
            to: '.github/workflows/tests.yml',
        )
        ->sink(
            from: 'github:laravel/laravel@11.x:phpunit.xml.dist',
        )
        ->sink(
            from: 'github:your-org/templates@main:.github/workflows/deploy.yml',
            to: '.github/workflows/deploy.yml',
        )
        ->sink(
            from: 'github:your-org/templates@main:.editorconfig',
        );
});

$composer->step('Run build', function (Step $step): void {
    $step->node(run: 'build', allowFailure: true);
});

$composer->step('Check file methods', function (Step $step): void {
    $step
        ->create('src/Actions/File/CreateFileAction.php', 'Test')
        ->copy('src/Actions/File/CopyFileAction.php', 'Test')
        ->append('src/Actions/File/AppendFileAction.php', 'Test');
});

return $composer;
