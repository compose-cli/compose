<?php

use Compose\Actions\Node\NodeInstall;
use Compose\Enums\Node;
use Compose\Enums\PackageOperation;

describe('NodeInstall', function (): void {

    it('generates an install command for npm', function (): void {
        $action = (new NodeInstall(
            packages: ['vue'],
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)
            ->toGenerateCommand('npm install vue')
            ->toBeOperation(PackageOperation::Install);
    });

    it('generates a dev install command for npm', function (): void {
        $action = (new NodeInstall(
            packages: ['vite'],
            dev: true,
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)
            ->toGenerateCommand('npm install --save-dev vite')
            ->toBeOperation(PackageOperation::InstallDev);
    });

    it('generates an install command for yarn', function (): void {
        $action = (new NodeInstall(
            packages: ['vue'],
            manager: Node::Yarn,
        ))->withContext(context(nodeManager: Node::Yarn));

        expect($action)->toGenerateCommand('yarn add vue');
    });

    it('generates a dev install command for yarn', function (): void {
        $action = (new NodeInstall(
            packages: ['vite'],
            dev: true,
            manager: Node::Yarn,
        ))->withContext(context(nodeManager: Node::Yarn));

        expect($action)->toGenerateCommand('yarn add --dev vite');
    });

    it('generates an install command for pnpm', function (): void {
        $action = (new NodeInstall(
            packages: ['vue'],
            manager: Node::Pnpm,
        ))->withContext(context(nodeManager: Node::Pnpm));

        expect($action)->toGenerateCommand('pnpm add vue');
    });

    it('generates a dev install command for pnpm', function (): void {
        $action = (new NodeInstall(
            packages: ['vite'],
            dev: true,
            manager: Node::Pnpm,
        ))->withContext(context(nodeManager: Node::Pnpm));

        expect($action)->toGenerateCommand('pnpm add --save-dev vite');
    });

    it('generates an install command for bun', function (): void {
        $action = (new NodeInstall(
            packages: ['vue'],
            manager: Node::Bun,
        ))->withContext(context(nodeManager: Node::Bun));

        expect($action)->toGenerateCommand('bun add vue');
    });

    it('generates a dev install command for bun', function (): void {
        $action = (new NodeInstall(
            packages: ['vite'],
            dev: true,
            manager: Node::Bun,
        ))->withContext(context(nodeManager: Node::Bun));

        expect($action)->toGenerateCommand('bun add --dev vite');
    });

    it('handles multiple packages', function (): void {
        $action = (new NodeInstall(
            packages: ['vue', 'axios'],
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)->toGenerateCommand('npm install vue axios');
    });

    it('can be rolled back', function (): void {
        $action = (new NodeInstall(
            packages: ['vue'],
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->rollback()->toString())->toBe('npm uninstall vue');

        $action = (new NodeInstall(
            packages: ['vue', 'axios'],
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->rollback()->toString())->toBe('npm uninstall vue axios');
    });

    it('rolls back with correct manager commands', function (): void {
        $action = (new NodeInstall(
            packages: ['vue'],
            manager: Node::Yarn,
        ))->withContext(context(nodeManager: Node::Yarn));

        expect($action->rollback()->toString())->toBe('yarn remove vue');

        $action = (new NodeInstall(
            packages: ['vue'],
            dev: true,
            manager: Node::Pnpm,
        ))->withContext(context(nodeManager: Node::Pnpm));

        expect($action->rollback()->toString())->toBe('pnpm remove --save-dev vue');
    });

    it('can handle a single package as a string', function (): void {
        $action = (new NodeInstall(
            packages: 'vue',
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)->toGenerateCommand('npm install vue');
    });

    it('defaults to npm when no manager specified', function (): void {
        $action = (new NodeInstall(
            packages: ['vue'],
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)->toGenerateCommand('npm install vue');
    });

});
