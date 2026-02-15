<?php

use Compose\Enums\PackageOperation;
use Compose\Actions\Composer\ComposerRun;

describe('ComposerRun', function () {

    it('generates a run command', function () {
        $action = new ComposerRun(
            script: 'test',
            bin: 'composer',
        );

        expect($action)
            ->toGenerateCommand('composer run ' . escapeshellarg('test'))
            ->toBeOperation(PackageOperation::Run);
    });

    it('generates a run command with a single argument', function () {
        $action = new ComposerRun(
            script: 'test',
            args: ['--filter=unit'],
            bin: 'composer',
        );

        expect($action)->toGenerateCommand(
            'composer run ' . escapeshellarg('test') . ' -- ' . escapeshellarg('--filter=unit'),
        );
    });

    it('generates a run command with multiple arguments', function () {
        $action = new ComposerRun(
            script: 'test',
            args: ['--filter=unit', '--coverage'],
            bin: 'composer',
        );

        expect($action)->toGenerateCommand(
            'composer run ' . escapeshellarg('test') . ' -- ' . escapeshellarg('--filter=unit') . ' ' . escapeshellarg('--coverage'),
        );
    });

    it('handles a single arg as a string', function () {
        $action = new ComposerRun(
            script: 'test',
            args: '--filter=unit',
            bin: 'composer',
        );

        expect($action)->toGenerateCommand(
            'composer run ' . escapeshellarg('test') . ' -- ' . escapeshellarg('--filter=unit'),
        );
    });

    it('escapes shell-unsafe characters in script name', function () {
        $action = new ComposerRun(
            script: 'test; rm -rf /',
            bin: 'composer',
        );

        expect($action)->toGenerateCommand('composer run ' . escapeshellarg('test; rm -rf /'));
    });

    it('escapes shell-unsafe characters in arguments', function () {
        $action = new ComposerRun(
            script: 'test',
            args: ['--path=/etc; cat /etc/passwd'],
            bin: 'composer',
        );

        expect($action)->toGenerateCommand(
            'composer run ' . escapeshellarg('test') . ' -- ' . escapeshellarg('--path=/etc; cat /etc/passwd'),
        );
    });

    it('uses a custom bin path', function () {
        $action = new ComposerRun(
            script: 'test',
            bin: '/usr/local/bin/composer',
        );

        expect($action)->toGenerateCommand('/usr/local/bin/composer run ' . escapeshellarg('test'));
    });

    it('cannot be rolled back', function () {
        $action = new ComposerRun(
            script: 'test',
            bin: 'composer',
        );

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->getRollbackCommand())->toBeNull();
    });

});
