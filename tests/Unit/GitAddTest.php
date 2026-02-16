<?php

use Compose\Actions\Git\GitAdd;
use Compose\Enums\GitOperation;

describe('GitAdd', function (): void {

    it('generates an add command', function (): void {
        $action = (new GitAdd)->withContext(context());

        expect($action)
            ->toGenerateCommand('git add -A')
            ->toBeOperation(GitOperation::Add);
    });

    it('uses a custom git binary from context', function (): void {
        $action = (new GitAdd)->withContext(context(gitBinary: '/usr/local/bin/git'));

        expect($action)->toGenerateCommand('/usr/local/bin/git add -A');
    });

    it('cannot be rolled back', function (): void {
        $action = (new GitAdd)->withContext(context());

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->rollback())->toBeNull();
    });

    it('returns the correct command array', function (): void {
        $action = (new GitAdd)->withContext(context());

        expect($action->command()->toArray())->toBe(['git', 'add', '-A']);
    });

});
