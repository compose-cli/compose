<?php

use Compose\Actions\Node\NodeRun;
use Compose\Enums\Node;
use Compose\Enums\PackageOperation;

describe('NodeRun', function (): void {

    it('generates a run command for npm', function (): void {
        $action = (new NodeRun(
            script: 'dev',
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)
            ->toGenerateCommand('npm run dev')
            ->toBeOperation(PackageOperation::Run);
    });

    it('generates a run command for yarn', function (): void {
        $action = (new NodeRun(
            script: 'dev',
            manager: Node::Yarn,
        ))->withContext(context(nodeManager: Node::Yarn));

        expect($action)->toGenerateCommand('yarn dev');
    });

    it('generates a run command for pnpm', function (): void {
        $action = (new NodeRun(
            script: 'dev',
            manager: Node::Pnpm,
        ))->withContext(context(nodeManager: Node::Pnpm));

        expect($action)->toGenerateCommand('pnpm run dev');
    });

    it('generates a run command for bun', function (): void {
        $action = (new NodeRun(
            script: 'dev',
            manager: Node::Bun,
        ))->withContext(context(nodeManager: Node::Bun));

        expect($action)->toGenerateCommand('bun dev');
    });

    it('generates a command with args for npm using -- separator', function (): void {
        $action = (new NodeRun(
            script: 'dev',
            args: ['--host', '--port=3000'],
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)->toGenerateCommand('npm run dev -- --host --port=3000');
    });

    it('generates a command with args for yarn without -- separator', function (): void {
        $action = (new NodeRun(
            script: 'dev',
            args: ['--host', '--port=3000'],
            manager: Node::Yarn,
        ))->withContext(context(nodeManager: Node::Yarn));

        expect($action)->toGenerateCommand('yarn dev --host --port=3000');
    });

    it('generates a command with args for pnpm using -- separator', function (): void {
        $action = (new NodeRun(
            script: 'dev',
            args: ['--host'],
            manager: Node::Pnpm,
        ))->withContext(context(nodeManager: Node::Pnpm));

        expect($action)->toGenerateCommand('pnpm run dev -- --host');
    });

    it('generates a command with args for bun without -- separator', function (): void {
        $action = (new NodeRun(
            script: 'dev',
            args: ['--host'],
            manager: Node::Bun,
        ))->withContext(context(nodeManager: Node::Bun));

        expect($action)->toGenerateCommand('bun dev --host');
    });

    it('handles a single arg as a string', function (): void {
        $action = (new NodeRun(
            script: 'dev',
            args: '--host',
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)->toGenerateCommand('npm run dev -- --host');
    });

    it('handles multiple arguments', function (): void {
        $action = (new NodeRun(
            script: 'test',
            args: ['--watch', '--coverage', '--verbose'],
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)->toGenerateCommand('npm run test -- --watch --coverage --verbose');
    });

    it('defaults to npm when no manager specified', function (): void {
        $action = (new NodeRun(
            script: 'dev',
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)->toGenerateCommand('npm run dev');
    });

    it('cannot be rolled back', function (): void {
        $action = (new NodeRun(
            script: 'dev',
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->rollback())->toBeNull();
    });

    it('returns the correct command array', function (): void {
        $action = (new NodeRun(
            script: 'dev',
            args: ['--host'],
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action->command()->toArray())->toBe(['npm', 'run', 'dev', '--', '--host']);
    });

});
