<?php

use Compose\Actions\Quality\PintFormat;
use Compose\Enums\QualityOperation;

describe('PintFormat', function (): void {

    describe('command', function (): void {

        it('generates the pint command using the php binary', function (): void {
            $action = new PintFormat;
            $action->withContext(context());

            expect($action)->toGenerateCommand('php vendor/bin/pint');
        });

        it('uses a custom php binary from context', function (): void {
            $action = new PintFormat;
            $action->withContext(context(phpBinary: '/usr/local/bin/php'));

            expect($action)->toGenerateCommand('/usr/local/bin/php vendor/bin/pint');
        });

    });

    it('returns the Format operation type', function (): void {
        $action = new PintFormat;

        expect($action)->toBeOperation(QualityOperation::Format);
    });

    it('describes itself as pint (format)', function (): void {
        $action = new PintFormat;
        $action->withContext(context());

        expect($action->describe())->toBe('pint (format)');
    });

    it('cannot be rolled back', function (): void {
        $action = new PintFormat;

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->rollback())->toBeNull();
    });

    describe('isInstalled', function (): void {

        it('returns true when vendor/bin/pint exists', function (): void {
            $this->createFile('vendor/bin/pint', '#!/usr/bin/env php');

            $action = new PintFormat;
            $action->withContext(context(workingDirectory: $this->tempPath));

            expect($action->isInstalled())->toBeTrue();
        });

        it('returns false when vendor/bin/pint does not exist', function (): void {
            $action = new PintFormat;
            $action->withContext(context(workingDirectory: $this->tempPath));

            expect($action->isInstalled())->toBeFalse();
        });

    });

    it('provides a helpful not-installed message', function (): void {
        $action = new PintFormat;

        expect($action->notInstalledMessage())->toContain('Laravel Pint is not installed');
        expect($action->notInstalledMessage())->toContain('composer require laravel/pint --dev');
    });

});
