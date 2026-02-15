<?php

use Compose\Compose;
use Compose\Enums\Node;
use Compose\Enums\Anthropic;
use Compose\Actions\Git\GitClone;

require __DIR__ . '/vendor/autoload.php';

$composer = compose('Compose CLI')
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
