<?php

use Compose\Enums\Node;
use Compose\Enums\PackageOperation;
use Compose\Actions\Node\NodeRemove;

describe('NodeRemove', function () {

    it('generates a remove command for npm', function () {
        $action = new NodeRemove(
            packages: ['vue'],
            manager: Node::Npm,
        );

        expect($action)
            ->toGenerateCommand('npm uninstall ' . escapeshellarg('vue'))
            ->toBeOperation(PackageOperation::Remove);
    });

    it('generates a dev remove command for npm', function () {
        $action = new NodeRemove(
            packages: ['vite'],
            dev: true,
            manager: Node::Npm,
        );

        expect($action)
            ->toGenerateCommand('npm uninstall ' . escapeshellarg('vite') . ' --save-dev')
            ->toBeOperation(PackageOperation::RemoveDev);
    });

    it('generates a remove command for yarn', function () {
        $action = new NodeRemove(
            packages: ['vue'],
            manager: Node::Yarn,
        );

        expect($action)->toGenerateCommand('yarn remove ' . escapeshellarg('vue'));
    });

    it('generates a dev remove command for yarn', function () {
        $action = new NodeRemove(
            packages: ['vite'],
            dev: true,
            manager: Node::Yarn,
        );

        expect($action)->toGenerateCommand('yarn remove ' . escapeshellarg('vite') . ' --dev');
    });

    it('generates a remove command for pnpm', function () {
        $action = new NodeRemove(
            packages: ['vue'],
            manager: Node::Pnpm,
        );

        expect($action)->toGenerateCommand('pnpm remove ' . escapeshellarg('vue'));
    });

    it('generates a dev remove command for pnpm', function () {
        $action = new NodeRemove(
            packages: ['vite'],
            dev: true,
            manager: Node::Pnpm,
        );

        expect($action)->toGenerateCommand('pnpm remove ' . escapeshellarg('vite') . ' --save-dev');
    });

    it('generates a remove command for bun', function () {
        $action = new NodeRemove(
            packages: ['vue'],
            manager: Node::Bun,
        );

        expect($action)->toGenerateCommand('bun remove ' . escapeshellarg('vue'));
    });

    it('generates a dev remove command for bun', function () {
        $action = new NodeRemove(
            packages: ['vite'],
            dev: true,
            manager: Node::Bun,
        );

        expect($action)->toGenerateCommand('bun remove ' . escapeshellarg('vite') . ' --dev');
    });

    it('handles multiple packages', function () {
        $action = new NodeRemove(
            packages: ['vue', 'axios'],
            manager: Node::Npm,
        );

        expect($action)->toGenerateCommand(
            'npm uninstall ' . escapeshellarg('vue') . ' ' . escapeshellarg('axios'),
        );
    });

    it('escapes shell-unsafe characters', function () {
        $action = new NodeRemove(
            packages: ['vue; rm -rf /'],
            manager: Node::Npm,
        );

        expect($action)->toGenerateCommand('npm uninstall ' . escapeshellarg('vue; rm -rf /'));
    });

    it('uses a custom bin path', function () {
        $action = new NodeRemove(
            packages: ['vue'],
            manager: '/usr/local/bin/yarn',
        );

        expect($action)->toGenerateCommand('/usr/local/bin/yarn remove ' . escapeshellarg('vue'));
    });

    it('defaults bin to the manager value', function () {
        $action = new NodeRemove(
            packages: ['vue'],
            manager: Node::Pnpm,
        );

        expect($action)->toGenerateCommand('pnpm remove ' . escapeshellarg('vue'));
    });

    it('can be rolled back', function () {
        $action = new NodeRemove(
            packages: ['vue'],
            manager: Node::Npm,
        );

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->getRollbackCommand())->toBe('npm install ' . escapeshellarg('vue'));

        // can rollback multiple packages
        $action = new NodeRemove(
            packages: ['vue', 'axios'],
            manager: Node::Npm,
        );

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->getRollbackCommand())->toBe('npm install ' . escapeshellarg('vue') . ' ' . escapeshellarg('axios'));
    });

    it('rolls back with correct manager commands', function () {
        $action = new NodeRemove(
            packages: ['vue'],
            manager: Node::Yarn,
        );

        expect($action->getRollbackCommand())->toBe('yarn add ' . escapeshellarg('vue'));

        $action = new NodeRemove(
            packages: ['vue'],
            dev: true,
            manager: Node::Pnpm,
        );

        expect($action->getRollbackCommand())->toBe('pnpm add ' . escapeshellarg('vue') . ' --save-dev');
    });

    it('can handle a single package as a string', function () {
        $action = new NodeRemove(
            packages: 'vue',
            manager: Node::Npm,
        );

        expect($action)->toGenerateCommand('npm uninstall ' . escapeshellarg('vue'));
    });

    it('defaults to npm when no manager specified', function () {
        $action = new NodeRemove(
            packages: ['vue'],
        );

        expect($action)->toGenerateCommand('npm uninstall ' . escapeshellarg('vue'));
    });

});
