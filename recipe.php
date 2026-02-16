<?php

use Compose\Enums\Anthropic;
use Compose\Enums\Node;
use Compose\Enums\TaskType;
use Compose\Step;

$composer = compose('Compose CLI', type: TaskType::NewProject)
    ->in('tests/tmp', fresh: true)
    ->base(repo: 'https://github.com/laravel/laravel.git')
    ->commit(automatically: true, smart: true)
    ->ai(Anthropic::ClaudeOpus45)
    ->git('git')
    ->node(Node::Yarn)
    ->composer('composer');

$composer->step('Install dependencies', function (Step $step) {
    $step
        ->composer(dev: ['laravel/telescope']);
});

$composer->step('Setup frontend', function (Step $step) {
    $step
        ->node(install: ['vue'], dev: ['vite', '@vitejs/plugin-vue']);
});

$composer->step('Swap Tailwind for UnoCSS', function (Step $step) {
    $step
        ->node(remove: ['tailwindcss', 'postcss', 'autoprefixer'], dev: ['unocss']);
});

$composer->step('Run build', function (Step $step) {
    $step->node(run: 'build');
});

return $composer;
