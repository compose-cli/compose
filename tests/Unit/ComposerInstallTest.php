<?php

use Compose\Enums\PackageOperation;
use Compose\Actions\Composer\ComposerInstall;

describe('ComposerInstall', function () {

    it('generates an install command', function () {
        $action = new ComposerInstall(
            packages: ['laravel/framework'],
            bin: 'composer',
        );

        expect($action)
            ->toGenerateCommand('composer install ' . escapeshellarg('laravel/framework'))
            ->toBeOperation(PackageOperation::Install);
    });

    it('generates a dev install command', function () {
        $action = new ComposerInstall(
            packages: ['pestphp/pest'],
            dev: true,
            bin: 'composer',
        );

        expect($action)
            ->toGenerateCommand('composer install ' . escapeshellarg('pestphp/pest') . ' --dev')
            ->toBeOperation(PackageOperation::InstallDev);
    });

    it('handles multiple packages', function () {
        $action = new ComposerInstall(
            packages: ['laravel/framework', 'illuminate/support'],
            bin: 'composer',
        );

        expect($action)->toGenerateCommand(
            'composer install ' . escapeshellarg('laravel/framework') . ' ' . escapeshellarg('illuminate/support'),
        );
    });

    it('escapes shell-unsafe characters', function () {
        $action = new ComposerInstall(
            packages: ['vendor/package; rm -rf /'],
            bin: 'composer',
        );

        expect($action)->toGenerateCommand('composer install ' . escapeshellarg('vendor/package; rm -rf /'));
    });

    it('uses a custom bin path', function () {
        $action = new ComposerInstall(
            packages: ['laravel/framework'],
            bin: '/usr/local/bin/composer',
        );

        expect($action)->toGenerateCommand('/usr/local/bin/composer install ' . escapeshellarg('laravel/framework'));
    });

    it('can be rolled back', function () {
        $action = new ComposerInstall(
            packages: ['laravel/framework'],
            bin: 'composer',
        );
        
        
        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->getRollbackCommand())->toBe('composer remove ' . escapeshellarg('laravel/framework'));

        // can rollback multiple packages
        $action = new ComposerInstall(
            packages: ['laravel/framework', 'illuminate/support'],
            bin: 'composer',
        );

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->getRollbackCommand())->toBe('composer remove ' . escapeshellarg('laravel/framework') . ' ' . escapeshellarg('illuminate/support'));
    });

    it('can handle a single package as a string', function () {
        $action = new ComposerInstall(
            packages: 'laravel/framework',
            bin: 'composer',
        );

        expect($action)->toGenerateCommand('composer install ' . escapeshellarg('laravel/framework'));
    });

});
