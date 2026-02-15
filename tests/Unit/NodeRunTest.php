<?php

use Compose\Enums\Node;
use Compose\Enums\PackageOperation;
use Compose\Actions\Node\NodeRun;

describe('NodeRun', function () {

    it('generates a run command for npm', function () {
        $action = new NodeRun(
            script: 'dev',
            manager: Node::Npm,
        );

        expect($action)
            ->toGenerateCommand('npm run ' . escapeshellarg('dev'))
            ->toBeOperation(PackageOperation::Run);
    });

    it('generates a run command for yarn', function () {
        $action = new NodeRun(
            script: 'dev',
            manager: Node::Yarn,
        );

        expect($action)->toGenerateCommand('yarn ' . escapeshellarg('dev'));
    });

    it('generates a run command for pnpm', function () {
        $action = new NodeRun(
            script: 'dev',
            manager: Node::Pnpm,
        );

        expect($action)->toGenerateCommand('pnpm run ' . escapeshellarg('dev'));
    });

    it('generates a run command for bun', function () {
        $action = new NodeRun(
            script: 'dev',
            manager: Node::Bun,
        );

        expect($action)->toGenerateCommand('bun ' . escapeshellarg('dev'));
    });

    it('generates a command with args for npm using -- separator', function () {
        $action = new NodeRun(
            script: 'dev',
            args: ['--host', '--port=3000'],
            manager: Node::Npm,
        );

        expect($action)->toGenerateCommand(
            'npm run ' . escapeshellarg('dev') . ' -- ' . escapeshellarg('--host') . ' ' . escapeshellarg('--port=3000'),
        );
    });

    it('generates a command with args for yarn without -- separator', function () {
        $action = new NodeRun(
            script: 'dev',
            args: ['--host', '--port=3000'],
            manager: Node::Yarn,
        );

        expect($action)->toGenerateCommand(
            'yarn ' . escapeshellarg('dev') . ' ' . escapeshellarg('--host') . ' ' . escapeshellarg('--port=3000'),
        );
    });

    it('generates a command with args for pnpm using -- separator', function () {
        $action = new NodeRun(
            script: 'dev',
            args: ['--host'],
            manager: Node::Pnpm,
        );

        expect($action)->toGenerateCommand(
            'pnpm run ' . escapeshellarg('dev') . ' -- ' . escapeshellarg('--host'),
        );
    });

    it('generates a command with args for bun without -- separator', function () {
        $action = new NodeRun(
            script: 'dev',
            args: ['--host'],
            manager: Node::Bun,
        );

        expect($action)->toGenerateCommand(
            'bun ' . escapeshellarg('dev') . ' ' . escapeshellarg('--host'),
        );
    });

    it('handles a single arg as a string', function () {
        $action = new NodeRun(
            script: 'dev',
            args: '--host',
            manager: Node::Npm,
        );

        expect($action)->toGenerateCommand(
            'npm run ' . escapeshellarg('dev') . ' -- ' . escapeshellarg('--host'),
        );
    });

    it('handles multiple arguments', function () {
        $action = new NodeRun(
            script: 'test',
            args: ['--watch', '--coverage', '--verbose'],
            manager: Node::Npm,
        );

        expect($action)->toGenerateCommand(
            'npm run ' . escapeshellarg('test') . ' -- ' . escapeshellarg('--watch') . ' ' . escapeshellarg('--coverage') . ' ' . escapeshellarg('--verbose'),
        );
    });

    it('escapes shell-unsafe characters in script name', function () {
        $action = new NodeRun(
            script: 'dev; rm -rf /',
            manager: Node::Npm,
        );

        expect($action)->toGenerateCommand('npm run ' . escapeshellarg('dev; rm -rf /'));
    });

    it('escapes shell-unsafe characters in arguments', function () {
        $action = new NodeRun(
            script: 'dev',
            args: ['--path=/etc; cat /etc/passwd'],
            manager: Node::Npm,
        );

        expect($action)->toGenerateCommand(
            'npm run ' . escapeshellarg('dev') . ' -- ' . escapeshellarg('--path=/etc; cat /etc/passwd'),
        );
    });

    it('uses a custom bin path', function () {
        $action = new NodeRun(
            script: 'dev',
            manager: '/usr/local/bin/yarn',
        );

        // Custom string paths default to npm-style (with `run`) since all managers support it
        expect($action)->toGenerateCommand('/usr/local/bin/yarn run ' . escapeshellarg('dev'));
    });

    it('defaults to npm when no manager specified', function () {
        $action = new NodeRun(
            script: 'dev',
        );

        expect($action)->toGenerateCommand('npm run ' . escapeshellarg('dev'));
    });

    it('cannot be rolled back', function () {
        $action = new NodeRun(
            script: 'dev',
            manager: Node::Npm,
        );

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->getRollbackCommand())->toBeNull();
    });

});
