<?php

use Compose\Actions\Composer\ComposerRun;
use Compose\Enums\PackageOperation;

describe('ComposerRun', function (): void {

    it('generates a run command', function (): void {
        $action = (new ComposerRun(
            script: 'test',
        ))->withContext(context());

        expect($action)
            ->toGenerateCommand('composer run test')
            ->toBeOperation(PackageOperation::Run);
    });

    it('generates a run command with a single argument', function (): void {
        $action = (new ComposerRun(
            script: 'test',
            args: ['--filter=unit'],
        ))->withContext(context());

        expect($action)->toGenerateCommand('composer run test -- --filter=unit');
    });

    it('generates a run command with multiple arguments', function (): void {
        $action = (new ComposerRun(
            script: 'test',
            args: ['--filter=unit', '--coverage'],
        ))->withContext(context());

        expect($action)->toGenerateCommand('composer run test -- --filter=unit --coverage');
    });

    it('handles a single arg as a string', function (): void {
        $action = (new ComposerRun(
            script: 'test',
            args: '--filter=unit',
        ))->withContext(context());

        expect($action)->toGenerateCommand('composer run test -- --filter=unit');
    });

    it('uses a custom bin path from context', function (): void {
        $action = (new ComposerRun(
            script: 'test',
        ))->withContext(context(composerBinary: '/usr/local/bin/composer'));

        expect($action)->toGenerateCommand('/usr/local/bin/composer run test');
    });

    it('cannot be rolled back', function (): void {
        $action = (new ComposerRun(
            script: 'test',
        ))->withContext(context());

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->rollback())->toBeNull();
    });

    it('returns the correct command array', function (): void {
        $action = (new ComposerRun(
            script: 'test',
            args: ['--filter=unit'],
        ))->withContext(context());

        expect($action->command()->toArray())->toBe(['composer', 'run', 'test', '--', '--filter=unit']);
    });

});
