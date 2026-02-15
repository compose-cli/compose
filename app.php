<?php

use Compose\Compose;
use Compose\Enums\Node;
use Compose\Enums\Anthropic;
use Compose\Actions\Git\GitClone;
use Compose\Enums\TaskType;
use Compose\Step;

require __DIR__ . '/vendor/autoload.php';

$composer = compose('Compose CLI', type: TaskType::NewProject)
    ->in('.', fresh: true)
    ->base(repo: 'https://github.com/laravel/laravel.git', branch: '10.x')
    ->commit(automatically: true, smart: true)
    ->ai(Anthropic::ClaudeOpus45)
    ->git('git')
    ->node(Node::Yarn)
    ->composer('composer');

$composer->before(function (Compose $composer) {
    $action = new GitClone(repo: $composer->baseRepo, branch: $composer->baseBranch, bin: $composer->getGitBinary());
});

$composer->step('Install dependencies', function (Step $step) {
    $step
        ->composer(dev: ['laravel/telescope']);
}, message: 'Installing dependencies...');

$composer->step('Setup frontend', function (Step $step) {
    $step
        ->node(install: ['vue', 'vue-router', 'axios'], dev: ['vite', '@vitejs/plugin-vue']);
}, message: 'Setting up frontend dependencies...');

$composer->step('Swap Tailwind for UnoCSS', function (Step $step) {
    $step
        ->node(remove: ['tailwindcss', 'postcss', 'autoprefixer'], dev: ['unocss']);
}, message: 'Replacing Tailwind with UnoCSS...');
