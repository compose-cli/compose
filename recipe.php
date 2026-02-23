<?php

use Compose\Enums\FailureStrategy;
use Compose\Enums\TaskType;
use Compose\Step;

$composer = compose('Compose CLI', type: TaskType::NewProject)
    ->in('tests/tmp', fresh: true)
    ->base(repo: 'https://github.com/laravel/laravel.git')
    ->commit(automatically: true, smart: true);

return $composer
    ->step('Install dependencies', function (Step $step): void {
        $step->composer(dev: ['laravel/telescope']);
    })
    ->step('Setup frontend', function (Step $step): void {
        $step->node(install: ['vue'], dev: ['vite', '@vitejs/plugin-vue']);
    }, onFailure: FailureStrategy::Continue)
    ->step('Swap Tailwind for UnoCSS', function (Step $step): void {
        $step->node(remove: ['tailwindcss', 'postcss', 'autoprefixer'], dev: ['unocss'], allowFailure: true);
    })
    ->step('Run build', function (Step $step): void {
        $step->node(run: 'build', allowFailure: true);
    });
