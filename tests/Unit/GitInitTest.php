<?php

use Compose\Actions\Git\GitInit;
use Compose\Enums\GitOperation;

describe('GitInit', function (): void {

    it('generates an init command', function (): void {
        $action = (new GitInit)->withContext(context());

        expect($action)
            ->toGenerateCommand('git init')
            ->toBeOperation(GitOperation::Init);
    });

    it('uses a custom git binary from context', function (): void {
        $action = (new GitInit)->withContext(context(gitBinary: '/usr/local/bin/git'));

        expect($action)->toGenerateCommand('/usr/local/bin/git init');
    });

    it('cannot be rolled back', function (): void {
        $action = (new GitInit)->withContext(context());

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->rollback())->toBeNull();
    });

    it('returns the correct command array', function (): void {
        $action = (new GitInit)->withContext(context());

        expect($action->command()->toArray())->toBe(['git', 'init']);
    });

});
