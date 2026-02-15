<?php

use Compose\Enums\Node;
use Compose\Enums\PackageOperation;
use Compose\Actions\Node\NodeInstall;

describe('NodeInstall', function () {

    it('generates an install command for npm', function () {
        $action = new NodeInstall(
            packages: ['vue'],
            manager: Node::Npm,
        );

        expect($action)
            ->toGenerateCommand('npm install ' . escapeshellarg('vue'))
            ->toBeOperation(PackageOperation::Install);
    });

    it('generates a dev install command for npm', function () {
        $action = new NodeInstall(
            packages: ['vite'],
            dev: true,
            manager: Node::Npm,
        );

        expect($action)
            ->toGenerateCommand('npm install ' . escapeshellarg('vite') . ' --save-dev')
            ->toBeOperation(PackageOperation::InstallDev);
    });

    it('generates an install command for yarn', function () {
        $action = new NodeInstall(
            packages: ['vue'],
            manager: Node::Yarn,
        );

        expect($action)->toGenerateCommand('yarn add ' . escapeshellarg('vue'));
    });

    it('generates a dev install command for yarn', function () {
        $action = new NodeInstall(
            packages: ['vite'],
            dev: true,
            manager: Node::Yarn,
        );

        expect($action)->toGenerateCommand('yarn add ' . escapeshellarg('vite') . ' --dev');
    });

    it('generates an install command for pnpm', function () {
        $action = new NodeInstall(
            packages: ['vue'],
            manager: Node::Pnpm,
        );

        expect($action)->toGenerateCommand('pnpm add ' . escapeshellarg('vue'));
    });

    it('generates a dev install command for pnpm', function () {
        $action = new NodeInstall(
            packages: ['vite'],
            dev: true,
            manager: Node::Pnpm,
        );

        expect($action)->toGenerateCommand('pnpm add ' . escapeshellarg('vite') . ' --save-dev');
    });

    it('generates an install command for bun', function () {
        $action = new NodeInstall(
            packages: ['vue'],
            manager: Node::Bun,
        );

        expect($action)->toGenerateCommand('bun add ' . escapeshellarg('vue'));
    });

    it('generates a dev install command for bun', function () {
        $action = new NodeInstall(
            packages: ['vite'],
            dev: true,
            manager: Node::Bun,
        );

        expect($action)->toGenerateCommand('bun add ' . escapeshellarg('vite') . ' --dev');
    });

    it('handles multiple packages', function () {
        $action = new NodeInstall(
            packages: ['vue', 'axios'],
            manager: Node::Npm,
        );

        expect($action)->toGenerateCommand(
            'npm install ' . escapeshellarg('vue') . ' ' . escapeshellarg('axios'),
        );
    });

    it('escapes shell-unsafe characters', function () {
        $action = new NodeInstall(
            packages: ['vue; rm -rf /'],
            manager: Node::Npm,
        );

        expect($action)->toGenerateCommand('npm install ' . escapeshellarg('vue; rm -rf /'));
    });

    it('uses a custom bin path', function () {
        $action = new NodeInstall(
            packages: ['vue'],
            manager: '/usr/local/bin/yarn',
        );

        expect($action)->toGenerateCommand('/usr/local/bin/yarn add ' . escapeshellarg('vue'));
    });

    it('defaults bin to the manager value', function () {
        $action = new NodeInstall(
            packages: ['vue'],
            manager: Node::Pnpm,
        );

        expect($action)->toGenerateCommand('pnpm add ' . escapeshellarg('vue'));
    });

    it('can be rolled back', function () {
        $action = new NodeInstall(
            packages: ['vue'],
            manager: Node::Npm,
        );

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->getRollbackCommand())->toBe('npm uninstall ' . escapeshellarg('vue'));

        // can rollback multiple packages
        $action = new NodeInstall(
            packages: ['vue', 'axios'],
            manager: Node::Npm,
        );

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->getRollbackCommand())->toBe('npm uninstall ' . escapeshellarg('vue') . ' ' . escapeshellarg('axios'));
    });

    it('rolls back with correct manager commands', function () {
        $action = new NodeInstall(
            packages: ['vue'],
            manager: Node::Yarn,
        );

        expect($action->getRollbackCommand())->toBe('yarn remove ' . escapeshellarg('vue'));

        $action = new NodeInstall(
            packages: ['vue'],
            dev: true,
            manager: Node::Pnpm,
        );

        expect($action->getRollbackCommand())->toBe('pnpm remove ' . escapeshellarg('vue') . ' --save-dev');
    });

    it('can handle a single package as a string', function () {
        $action = new NodeInstall(
            packages: 'vue',
            manager: Node::Npm,
        );

        expect($action)->toGenerateCommand('npm install ' . escapeshellarg('vue'));
    });

    it('defaults to npm when no manager specified', function () {
        $action = new NodeInstall(
            packages: ['vue'],
        );

        expect($action)->toGenerateCommand('npm install ' . escapeshellarg('vue'));
    });

});
