<?php

use Compose\Actions\PendingCommand;

describe('PendingCommand', function (): void {

    it('builds a basic command', function (): void {
        $cmd = new PendingCommand('composer', 'require');

        expect($cmd->toArray())->toBe(['composer', 'require']);
        expect($cmd->toString())->toBe('composer require');
    });

    it('adds arguments', function (): void {
        $cmd = (new PendingCommand('composer', 'require'))
            ->argument('laravel/framework', 'laravel/sanctum');

        expect($cmd->toArray())->toBe(['composer', 'require', 'laravel/framework', 'laravel/sanctum']);
    });

    it('adds flags', function (): void {
        $cmd = (new PendingCommand('composer', 'require'))
            ->flag('--dev')
            ->argument('laravel/pint');

        expect($cmd->toArray())->toBe(['composer', 'require', '--dev', 'laravel/pint']);
    });

    it('supports conditional modification', function (): void {
        $cmd = (new PendingCommand('composer', 'require'))
            ->when(true, fn (PendingCommand $cmd) => $cmd->flag('--dev'))
            ->when(false, fn (PendingCommand $cmd) => $cmd->flag('--no-interaction'))
            ->argument('laravel/pint');

        expect($cmd->toArray())->toBe(['composer', 'require', '--dev', 'laravel/pint']);
    });

    it('converts to string via __toString', function (): void {
        $cmd = new PendingCommand('git', 'clone', 'https://github.com/laravel/laravel.git');

        expect((string) $cmd)->toBe('git clone https://github.com/laravel/laravel.git');
    });

});
