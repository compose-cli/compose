<?php

use Compose\Actions\Git\GitClone;
use Compose\Enums\GitOperation;

describe('GitClone', function (): void {

    it('generates a clone command', function (): void {
        $action = (new GitClone(
            repo: 'https://github.com/laravel/laravel.git',
        ))->withContext(context());

        expect($action)
            ->toGenerateCommand('git clone https://github.com/laravel/laravel.git')
            ->toBeOperation(GitOperation::Clone);
    });

    it('generates a clone command with a branch', function (): void {
        $action = (new GitClone(
            repo: 'https://github.com/laravel/laravel.git',
            branch: '11.x',
        ))->withContext(context());

        expect($action)->toGenerateCommand('git clone --branch 11.x https://github.com/laravel/laravel.git');
    });

    it('generates a clone command with a custom directory', function (): void {
        $action = (new GitClone(
            repo: 'https://github.com/laravel/laravel.git',
            directory: 'my-project',
        ))->withContext(context());

        expect($action)->toGenerateCommand('git clone https://github.com/laravel/laravel.git my-project');
    });

    it('generates a clone command with branch and directory', function (): void {
        $action = (new GitClone(
            repo: 'https://github.com/laravel/laravel.git',
            branch: '11.x',
            directory: 'my-project',
        ))->withContext(context());

        expect($action)->toGenerateCommand('git clone --branch 11.x https://github.com/laravel/laravel.git my-project');
    });

    it('uses a custom git binary from context', function (): void {
        $action = (new GitClone(
            repo: 'https://github.com/laravel/laravel.git',
        ))->withContext(context(gitBinary: '/usr/local/bin/git'));

        expect($action)->toGenerateCommand('/usr/local/bin/git clone https://github.com/laravel/laravel.git');
    });

    it('determines the target directory from the repo', function (): void {
        $action = new GitClone(repo: 'https://github.com/laravel/laravel.git');

        expect($action->targetDirectory())->toBe('laravel');
    });

    it('uses the custom directory as target directory', function (): void {
        $action = new GitClone(
            repo: 'https://github.com/laravel/laravel.git',
            directory: 'my-project',
        );

        expect($action->targetDirectory())->toBe('my-project');
    });

    it('can be rolled back', function (): void {
        $action = (new GitClone(
            repo: 'https://github.com/laravel/laravel.git',
        ))->withContext(context());

        $expected = PHP_OS_FAMILY === 'Windows'
            ? 'cmd /c rmdir /s /q laravel'
            : 'rm -rf laravel';

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->rollback()->toString())->toBe($expected);
    });

    it('rolls back with custom directory', function (): void {
        $action = (new GitClone(
            repo: 'https://github.com/laravel/laravel.git',
            directory: 'my-project',
        ))->withContext(context());

        $expected = PHP_OS_FAMILY === 'Windows'
            ? 'cmd /c rmdir /s /q my-project'
            : 'rm -rf my-project';

        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->rollback()->toString())->toBe($expected);
    });

    it('returns the correct command array', function (): void {
        $action = (new GitClone(
            repo: 'https://github.com/laravel/laravel.git',
            branch: '11.x',
        ))->withContext(context());

        expect($action->command()->toArray())->toBe([
            'git', 'clone', '--branch', '11.x', 'https://github.com/laravel/laravel.git',
        ]);
    });

});
