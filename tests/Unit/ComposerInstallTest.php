<?php

use Compose\Actions\Composer\ComposerInstall;
use Compose\Enums\PackageOperation;

describe('ComposerInstall', function (): void {

    it('generates an install command', function (): void {
        $action = (new ComposerInstall(
            packages: ['laravel/framework'],
        ))->withContext(context());

        expect($action)
            ->toGenerateCommand('composer require laravel/framework')
            ->toBeOperation(PackageOperation::Install);
    });

    it('generates a dev install command', function (): void {
        $action = (new ComposerInstall(
            packages: ['pestphp/pest'],
            dev: true,
        ))->withContext(context());

        expect($action)
            ->toGenerateCommand('composer require --dev pestphp/pest')
            ->toBeOperation(PackageOperation::InstallDev);
    });

    it('handles multiple packages', function (): void {
        $action = (new ComposerInstall(
            packages: ['laravel/framework', 'illuminate/support'],
        ))->withContext(context());

        expect($action)->toGenerateCommand('composer require laravel/framework illuminate/support');
    });

    it('uses a custom bin path from context', function (): void {
        $action = (new ComposerInstall(
            packages: ['laravel/framework'],
        ))->withContext(context(composerBinary: '/usr/local/bin/composer'));

        expect($action)->toGenerateCommand('/usr/local/bin/composer require laravel/framework');
    });

    it('can be rolled back', function (): void {
        $action = (new ComposerInstall(
            packages: ['laravel/framework'],
        ))->withContext(context());

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->rollback()->toString())->toBe('composer remove laravel/framework');

        $action = (new ComposerInstall(
            packages: ['laravel/framework', 'illuminate/support'],
        ))->withContext(context());

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->rollback()->toString())->toBe('composer remove laravel/framework illuminate/support');
    });

    it('can handle a single package as a string', function (): void {
        $action = (new ComposerInstall(
            packages: 'laravel/framework',
        ))->withContext(context());

        expect($action)->toGenerateCommand('composer require laravel/framework');
    });

    it('returns the correct command array', function (): void {
        $action = (new ComposerInstall(
            packages: ['laravel/framework'],
            dev: true,
        ))->withContext(context());

        expect($action->command()->toArray())->toBe(['composer', 'require', '--dev', 'laravel/framework']);
    });

});
