<?php

use Compose\Actions\Artisan\ArtisanAction;
use Compose\Enums\ArtisanOperation;

describe('ArtisanAction', function (): void {

    it('generates an artisan command', function (): void {
        $action = (new ArtisanAction('make:model Team'))->withContext(context());

        expect($action)
            ->toGenerateCommand('php artisan make:model Team')
            ->toBeOperation(ArtisanOperation::Run);
    });

    it('generates a command with flags', function (): void {
        $action = (new ArtisanAction('make:model Team -mf'))->withContext(context());

        expect($action)->toGenerateCommand('php artisan make:model Team -mf');
    });

    it('generates a command with long options', function (): void {
        $action = (new ArtisanAction('make:controller TeamController --api --model=Team'))->withContext(context());

        expect($action)->toGenerateCommand('php artisan make:controller TeamController --api --model=Team');
    });

    it('generates a single-word command', function (): void {
        $action = (new ArtisanAction('migrate'))->withContext(context());

        expect($action)->toGenerateCommand('php artisan migrate');
    });

    it('generates a vendor:publish command', function (): void {
        $action = (new ArtisanAction('vendor:publish --tag=cashier-migrations'))->withContext(context());

        expect($action)->toGenerateCommand('php artisan vendor:publish --tag=cashier-migrations');
    });

    it('uses a custom php binary from context', function (): void {
        $action = (new ArtisanAction('migrate'))->withContext(context(phpBinary: '/usr/bin/php8.3'));

        expect($action)->toGenerateCommand('/usr/bin/php8.3 artisan migrate');
    });

    it('cannot be rolled back', function (): void {
        $action = (new ArtisanAction('migrate'))->withContext(context());

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->rollback())->toBeNull();
    });

    it('returns the correct command array', function (): void {
        $action = (new ArtisanAction('make:model Team -mf'))->withContext(context());

        expect($action->command()->toArray())->toBe(['php', 'artisan', 'make:model', 'Team', '-mf']);
    });

    it('returns a preflight command', function (): void {
        $action = (new ArtisanAction('migrate'))->withContext(context());

        $preflight = $action->preflight();

        expect($preflight)->not->toBeNull();
        expect($preflight->toString())->toBe('php artisan --version');
    });

    it('uses the custom php binary in the preflight command', function (): void {
        $action = (new ArtisanAction('migrate'))->withContext(context(phpBinary: '/usr/bin/php8.3'));

        expect($action->preflight()->toString())->toBe('/usr/bin/php8.3 artisan --version');
    });

});
