<?php

use Compose\Actions\Git\GitCommit;
use Compose\Enums\GitOperation;

describe('GitCommit', function (): void {

    it('generates a commit command with a message', function (): void {
        $action = (new GitCommit(message: 'Initial commit'))->withContext(context());

        expect($action)
            ->toGenerateCommand('git commit -m Initial commit')
            ->toBeOperation(GitOperation::Commit);
    });

    it('uses a custom git binary from context', function (): void {
        $action = (new GitCommit(message: 'feat: add stuff'))->withContext(context(gitBinary: '/usr/local/bin/git'));

        expect($action)->toGenerateCommand('/usr/local/bin/git commit -m feat: add stuff');
    });

    it('handles a null message', function (): void {
        $action = (new GitCommit)->withContext(context());

        expect($action->message)->toBeNull();
        expect($action)->toGenerateCommand('git commit -m ');
    });

    it('cannot be rolled back', function (): void {
        $action = (new GitCommit(message: 'test'))->withContext(context());

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->rollback())->toBeNull();
    });

    it('returns the correct command array', function (): void {
        $action = (new GitCommit(message: 'Initial commit'))->withContext(context());

        expect($action->command()->toArray())->toBe(['git', 'commit', '-m', 'Initial commit']);
    });

});
