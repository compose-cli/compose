<?php

use Compose\Enums\PackageOperation;
use Compose\Actions\Composer\ComposerRemove;

describe('ComposerRemove', function () {

    it('generates a remove command', function () {
        $action = new ComposerRemove(
            packages: ['laravel/framework'],
            bin: 'composer',
        );

        expect($action)
            ->toGenerateCommand('composer remove ' . escapeshellarg('laravel/framework'))
            ->toBeOperation(PackageOperation::Remove);
    });

    it('generates a dev remove command', function () {
        $action = new ComposerRemove(
            packages: ['pestphp/pest'],
            dev: true,
            bin: 'composer',
        );

        expect($action)
            ->toGenerateCommand('composer remove ' . escapeshellarg('pestphp/pest') . ' --dev')
            ->toBeOperation(PackageOperation::RemoveDev);
    });

    it('handles multiple packages', function () {
        $action = new ComposerRemove(
            packages: ['laravel/framework', 'illuminate/support'],
            bin: 'composer',
        );

        expect($action)->toGenerateCommand(
            'composer remove ' . escapeshellarg('laravel/framework') . ' ' . escapeshellarg('illuminate/support'),
        );
    });

    it('escapes shell-unsafe characters', function () {
        $action = new ComposerRemove(
            packages: ['vendor/package; rm -rf /'],
            bin: 'composer',
        );

        expect($action)->toGenerateCommand('composer remove ' . escapeshellarg('vendor/package; rm -rf /'));
    });

    it('uses a custom bin path', function () {
        $action = new ComposerRemove(
            packages: ['laravel/framework'],
            bin: '/usr/local/bin/composer',
        );

        expect($action)->toGenerateCommand('/usr/local/bin/composer remove ' . escapeshellarg('laravel/framework'));
    });

    it('can be rolled back', function () {
        $action = new ComposerRemove(
            packages: ['laravel/framework'],
            bin: 'composer',
        );

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->getRollbackCommand())->toBe('composer install ' . escapeshellarg('laravel/framework'));

        // can rollback multiple packages
        $action = new ComposerRemove(
            packages: ['laravel/framework', 'illuminate/support'],
            bin: 'composer',
        );

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->getRollbackCommand())->toBe('composer install ' . escapeshellarg('laravel/framework') . ' ' . escapeshellarg('illuminate/support'));
    });

    it('can handle a single package as a string', function () {
        $action = new ComposerRemove(
            packages: 'laravel/framework',
            bin: 'composer',
        );

        expect($action)->toGenerateCommand('composer remove ' . escapeshellarg('laravel/framework'));
    });

});
