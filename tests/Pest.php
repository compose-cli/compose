<?php

use Compose\Contracts\Operation;
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
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toGenerateCommand', function (string $expected) {
    return $this->and($this->value->getCommand())->toBe($expected);
});

expect()->extend('toBeOperation', function (Operation $expected) {
    return $this->and($this->value->type())->toBe($expected);
});
