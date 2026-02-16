<?php

use Compose\Actions\Composer\ComposerRemove;
use Compose\Enums\PackageOperation;

describe('ComposerRemove', function (): void {

    it('generates a remove command', function (): void {
        $action = (new ComposerRemove(
            packages: ['laravel/framework'],
        ))->withContext(context());

        expect($action)
            ->toGenerateCommand('composer remove laravel/framework')
            ->toBeOperation(PackageOperation::Remove);
    });

    it('generates a dev remove command', function (): void {
        $action = (new ComposerRemove(
            packages: ['pestphp/pest'],
            dev: true,
        ))->withContext(context());

        expect($action)
            ->toGenerateCommand('composer remove --dev pestphp/pest')
            ->toBeOperation(PackageOperation::RemoveDev);
    });

    it('handles multiple packages', function (): void {
        $action = (new ComposerRemove(
            packages: ['laravel/framework', 'illuminate/support'],
        ))->withContext(context());

        expect($action)->toGenerateCommand('composer remove laravel/framework illuminate/support');
    });

    it('uses a custom bin path from context', function (): void {
        $action = (new ComposerRemove(
            packages: ['laravel/framework'],
        ))->withContext(context(composerBinary: '/usr/local/bin/composer'));

        expect($action)->toGenerateCommand('/usr/local/bin/composer remove laravel/framework');
    });

    it('can be rolled back', function (): void {
        $action = (new ComposerRemove(
            packages: ['laravel/framework'],
        ))->withContext(context());

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->rollback()->toString())->toBe('composer require laravel/framework');

        $action = (new ComposerRemove(
            packages: ['laravel/framework', 'illuminate/support'],
        ))->withContext(context());

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->rollback()->toString())->toBe('composer require laravel/framework illuminate/support');
    });

    it('can handle a single package as a string', function (): void {
        $action = (new ComposerRemove(
            packages: 'laravel/framework',
        ))->withContext(context());

        expect($action)->toGenerateCommand('composer remove laravel/framework');
    });

});
