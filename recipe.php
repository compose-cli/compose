<?php

use Compose\Enums\FailureStrategy;
use Compose\Enums\TaskType;
use Compose\Step;

$composer = compose('Compose CLI', type: TaskType::NewProject)
    ->in('tests/tmp', fresh: true)
    ->base(repo: 'https://github.com/laravel/laravel.git')
    ->commit(automatically: true, smart: true);

$composer->step('Install dependencies', function (Step $step): void {
    $step
        ->composer(dev: ['laravel/telescope']);
});

$composer->step('Setup frontend', function (Step $step): void {
    $step
        ->node(install: ['vue'], dev: ['vite', '@vitejs/plugin-vue']);
}, onFailure: FailureStrategy::Continue);

$composer->step('Swap Tailwind for UnoCSS', function (Step $step): void {
    $step
        ->node(remove: ['tailwindcss', 'postcss', 'autoprefixer'], dev: ['unocss'], allowFailure: true);
});

$composer->step('Run build', function (Step $step): void {
    $step->node(run: 'build', allowFailure: true);
});

return $composer;
