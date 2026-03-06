<?php

use Compose\Actions\Quality\RectorProcess;
use Compose\Enums\QualityOperation;

describe('RectorProcess', function (): void {

    describe('command', function (): void {

        it('generates the rector process command using the php binary', function (): void {
            $action = new RectorProcess;
            $action->withContext(context());

            expect($action)->toGenerateCommand('php vendor/bin/rector process');
        });

        it('uses a custom php binary from context', function (): void {
            $action = new RectorProcess;
            $action->withContext(context(phpBinary: '/usr/local/bin/php'));

            expect($action)->toGenerateCommand('/usr/local/bin/php vendor/bin/rector process');
        });

    });

    it('returns the Refactor operation type', function (): void {
        $action = new RectorProcess;

        expect($action)->toBeOperation(QualityOperation::Refactor);
    });

    it('describes itself as rector process (refactor)', function (): void {
        $action = new RectorProcess;
        $action->withContext(context());

        expect($action->describe())->toBe('rector process (refactor)');
    });

    it('cannot be rolled back', function (): void {
        $action = new RectorProcess;

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->rollback())->toBeNull();
    });

    describe('isInstalled', function (): void {

        it('returns true when vendor/bin/rector exists', function (): void {
            $this->createFile('vendor/bin/rector', '#!/usr/bin/env php');

            $action = new RectorProcess;
            $action->withContext(context(workingDirectory: $this->tempPath));

            expect($action->isInstalled())->toBeTrue();
        });

        it('returns false when vendor/bin/rector does not exist', function (): void {
            $action = new RectorProcess;
            $action->withContext(context(workingDirectory: $this->tempPath));

            expect($action->isInstalled())->toBeFalse();
        });

    });

    it('provides a helpful not-installed message', function (): void {
        $action = new RectorProcess;

        expect($action->notInstalledMessage())->toContain('Rector is not installed');
        expect($action->notInstalledMessage())->toContain('composer require rector/rector --dev');
    });

});
