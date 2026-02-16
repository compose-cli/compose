<?php

use Compose\Actions\Node\NodeRemove;
use Compose\Enums\Node;
use Compose\Enums\PackageOperation;

describe('NodeRemove', function (): void {

    it('generates a remove command for npm', function (): void {
        $action = (new NodeRemove(
            packages: ['vue'],
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)
            ->toGenerateCommand('npm uninstall vue')
            ->toBeOperation(PackageOperation::Remove);
    });

    it('generates a dev remove command for npm', function (): void {
        $action = (new NodeRemove(
            packages: ['vite'],
            dev: true,
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)
            ->toGenerateCommand('npm uninstall --save-dev vite')
            ->toBeOperation(PackageOperation::RemoveDev);
    });

    it('generates a remove command for yarn', function (): void {
        $action = (new NodeRemove(
            packages: ['vue'],
            manager: Node::Yarn,
        ))->withContext(context(nodeManager: Node::Yarn));

        expect($action)->toGenerateCommand('yarn remove vue');
    });

    it('generates a dev remove command for yarn', function (): void {
        $action = (new NodeRemove(
            packages: ['vite'],
            dev: true,
            manager: Node::Yarn,
        ))->withContext(context(nodeManager: Node::Yarn));

        expect($action)->toGenerateCommand('yarn remove --dev vite');
    });

    it('generates a remove command for pnpm', function (): void {
        $action = (new NodeRemove(
            packages: ['vue'],
            manager: Node::Pnpm,
        ))->withContext(context(nodeManager: Node::Pnpm));

        expect($action)->toGenerateCommand('pnpm remove vue');
    });

    it('generates a dev remove command for pnpm', function (): void {
        $action = (new NodeRemove(
            packages: ['vite'],
            dev: true,
            manager: Node::Pnpm,
        ))->withContext(context(nodeManager: Node::Pnpm));

        expect($action)->toGenerateCommand('pnpm remove --save-dev vite');
    });

    it('generates a remove command for bun', function (): void {
        $action = (new NodeRemove(
            packages: ['vue'],
            manager: Node::Bun,
        ))->withContext(context(nodeManager: Node::Bun));

        expect($action)->toGenerateCommand('bun remove vue');
    });

    it('generates a dev remove command for bun', function (): void {
        $action = (new NodeRemove(
            packages: ['vite'],
            dev: true,
            manager: Node::Bun,
        ))->withContext(context(nodeManager: Node::Bun));

        expect($action)->toGenerateCommand('bun remove --dev vite');
    });

    it('handles multiple packages', function (): void {
        $action = (new NodeRemove(
            packages: ['vue', 'axios'],
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)->toGenerateCommand('npm uninstall vue axios');
    });

    it('can be rolled back', function (): void {
        $action = (new NodeRemove(
            packages: ['vue'],
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->rollback()->toString())->toBe('npm install vue');

        $action = (new NodeRemove(
            packages: ['vue', 'axios'],
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->rollback()->toString())->toBe('npm install vue axios');
    });

    it('rolls back with correct manager commands', function (): void {
        $action = (new NodeRemove(
            packages: ['vue'],
            manager: Node::Yarn,
        ))->withContext(context(nodeManager: Node::Yarn));

        expect($action->rollback()->toString())->toBe('yarn add vue');

        $action = (new NodeRemove(
            packages: ['vue'],
            dev: true,
            manager: Node::Pnpm,
        ))->withContext(context(nodeManager: Node::Pnpm));

        expect($action->rollback()->toString())->toBe('pnpm add --save-dev vue');
    });

    it('can handle a single package as a string', function (): void {
        $action = (new NodeRemove(
            packages: 'vue',
            manager: Node::Npm,
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)->toGenerateCommand('npm uninstall vue');
    });

    it('defaults to npm when no manager specified', function (): void {
        $action = (new NodeRemove(
            packages: ['vue'],
        ))->withContext(context(nodeManager: Node::Npm));

        expect($action)->toGenerateCommand('npm uninstall vue');
    });

});
