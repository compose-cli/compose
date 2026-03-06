<?php

use Compose\Actions\Git\GitCheckout;
use Compose\Enums\GitOperation;

describe('GitCheckout', function (): void {

    it('generates a checkout command', function (): void {
        $action = (new GitCheckout(branch: 'feature/auth'))->withContext(context());

        expect($action)
            ->toGenerateCommand('git checkout feature/auth')
            ->toBeOperation(GitOperation::Checkout);
    });

    it('uses a custom git binary from context', function (): void {
        $action = (new GitCheckout(branch: 'main'))->withContext(context(gitBinary: '/usr/local/bin/git'));

        expect($action)->toGenerateCommand('/usr/local/bin/git checkout main');
    });

    it('can be rolled back with git checkout -', function (): void {
        $action = (new GitCheckout(branch: 'feature/auth'))->withContext(context());

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->rollback()->toString())->toBe('git checkout -');
    });

    it('returns the correct command array', function (): void {
        $action = (new GitCheckout(branch: 'develop'))->withContext(context());

        expect($action->command()->toArray())->toBe(['git', 'checkout', 'develop']);
    });

    it('is not a direct action', function (): void {
        $action = (new GitCheckout(branch: 'main'))->withContext(context());

        expect($action->isDirect())->toBeFalse();
    });

});
