<?php

use Compose\Contracts\Operation;
use Compose\Enums\Node;
use Compose\RecipeContext;
use Compose\Tests\Concerns\InteractsWithFilesystem;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(InteractsWithFilesystem::class)
    ->beforeEach(fn () => $this->initializeTempDirectory())
    ->afterEach(fn () => $this->cleanupTempDirectory())
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function context(
    string $composerBinary = 'composer',
    string $gitBinary = 'git',
    Node $nodeManager = Node::Npm,
    ?string $workingDirectory = null,
): RecipeContext {
    return new RecipeContext(
        composerBinary: $composerBinary,
        gitBinary: $gitBinary,
        nodeManager: $nodeManager,
        workingDirectory: $workingDirectory,
    );
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toGenerateCommand', fn (string $expected) => $this->and($this->value->command()->toString())->toBe($expected));

expect()->extend('toBeOperation', fn (Operation $expected) => $this->and($this->value->type())->toBe($expected));
