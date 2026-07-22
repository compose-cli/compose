<?php

use Compose\Actions\Git\GitCommit;
use Compose\Enums\GitOperation;
use Symfony\Component\Process\Process;

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

    it('uses a fallback message when null or empty', function (): void {
        $action = (new GitCommit)->withContext(context());

        expect($action->message)->toBeNull();
        expect($action)->toGenerateCommand('git commit -m compose: changes');

        $empty = (new GitCommit(message: '   '))->withContext(context());
        expect($empty)->toGenerateCommand('git commit -m compose: changes');
    });

    it('is a direct action', function (): void {
        $action = new GitCommit(message: 'test');

        expect($action->isDirect())->toBeTrue();
    });

    it('cannot be rolled back before a successful commit', function (): void {
        $action = new GitCommit(message: 'test');

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->canRollbackDirect())->toBeFalse();
        expect($action->rollbackDirect(context()))->toBeNull();
    });

    it('returns the correct command array', function (): void {
        $action = (new GitCommit(message: 'Initial commit'))->withContext(context());

        expect($action->command()->toArray())->toBe(['git', 'commit', '-m', 'Initial commit']);
    });

    it('stages and commits changes in a real git repo', function (): void {
        $repoPath = $this->tempPath.DIRECTORY_SEPARATOR.'commit-test';
        mkdir($repoPath, 0755, true);

        (new Process(['git', 'init'], $repoPath))->run();
        (new Process(['git', 'config', 'user.name', 'Test'], $repoPath))->run();
        (new Process(['git', 'config', 'user.email', 'test@example.com'], $repoPath))->run();
        file_put_contents($repoPath.DIRECTORY_SEPARATOR.'readme.txt', 'hello');

        $ctx = context(workingDirectory: $repoPath);
        $action = new GitCommit(message: 'Initial commit', stageAll: true);
        $result = $action->execute($ctx);

        expect($result->successful)->toBeTrue();
        expect($action->didCreateCommit())->toBeTrue();
        expect($action->canBeRolledBack())->toBeTrue();
        expect($action->parentSha())->toBeNull();

        $log = new Process(['git', 'log', '-1', '--pretty=%s'], $repoPath);
        $log->run();
        expect(trim($log->getOutput()))->toBe('Initial commit');
    });

    it('skips cleanly when there is nothing to commit', function (): void {
        $repoPath = $this->tempPath.DIRECTORY_SEPARATOR.'clean-commit-test';
        mkdir($repoPath, 0755, true);

        (new Process(['git', 'init'], $repoPath))->run();
        (new Process(['git', 'config', 'user.name', 'Test'], $repoPath))->run();
        (new Process(['git', 'config', 'user.email', 'test@example.com'], $repoPath))->run();
        (new Process(['git', 'commit', '--allow-empty', '-m', 'init'], $repoPath))->run();

        $ctx = context(workingDirectory: $repoPath);
        $action = new GitCommit(message: 'noop', stageAll: true);
        $result = $action->execute($ctx);

        expect($result->successful)->toBeTrue();
        expect($action->didCreateCommit())->toBeFalse();
        expect($action->canBeRolledBack())->toBeFalse();
        expect($result->output)->toContain('nothing to commit');
    });

    it('rolls back a commit via mixed reset to the parent SHA', function (): void {
        $repoPath = $this->tempPath.DIRECTORY_SEPARATOR.'commit-rollback-test';
        mkdir($repoPath, 0755, true);

        (new Process(['git', 'init'], $repoPath))->run();
        (new Process(['git', 'config', 'user.name', 'Test'], $repoPath))->run();
        (new Process(['git', 'config', 'user.email', 'test@example.com'], $repoPath))->run();
        (new Process(['git', 'commit', '--allow-empty', '-m', 'init'], $repoPath))->run();

        $parent = new Process(['git', 'rev-parse', 'HEAD'], $repoPath);
        $parent->run();
        $parentSha = trim($parent->getOutput());

        file_put_contents($repoPath.DIRECTORY_SEPARATOR.'feature.txt', 'new');

        $ctx = context(workingDirectory: $repoPath);
        $action = new GitCommit(message: 'feat: add feature', stageAll: true);
        $action->execute($ctx);

        expect($action->didCreateCommit())->toBeTrue();
        expect($action->parentSha())->toBe($parentSha);

        $rollback = $action->rollbackDirect($ctx);

        expect($rollback->successful)->toBeTrue();
        expect($action->didCreateCommit())->toBeFalse();

        $head = new Process(['git', 'rev-parse', 'HEAD'], $repoPath);
        $head->run();
        expect(trim($head->getOutput()))->toBe($parentSha);

        expect(file_exists($repoPath.DIRECTORY_SEPARATOR.'feature.txt'))->toBeTrue();
    });

    it('rolls back the first commit by deleting HEAD', function (): void {
        $repoPath = $this->tempPath.DIRECTORY_SEPARATOR.'first-commit-rollback';
        mkdir($repoPath, 0755, true);

        (new Process(['git', 'init'], $repoPath))->run();
        (new Process(['git', 'config', 'user.name', 'Test'], $repoPath))->run();
        (new Process(['git', 'config', 'user.email', 'test@example.com'], $repoPath))->run();
        file_put_contents($repoPath.DIRECTORY_SEPARATOR.'readme.txt', 'hello');

        $ctx = context(workingDirectory: $repoPath);
        $action = new GitCommit(message: 'first', stageAll: true);
        $action->execute($ctx);

        expect($action->didCreateCommit())->toBeTrue();
        expect($action->parentSha())->toBeNull();

        $rollback = $action->rollbackDirect($ctx);

        expect($rollback->successful)->toBeTrue();

        $head = new Process(['git', 'rev-parse', 'HEAD'], $repoPath);
        $head->run();
        expect($head->isSuccessful())->toBeFalse();

        expect(file_exists($repoPath.DIRECTORY_SEPARATOR.'readme.txt'))->toBeTrue();
    });

    it('commits successfully without local user identity configured', function (): void {
        $repoPath = $this->tempPath.DIRECTORY_SEPARATOR.'no-identity-test';
        mkdir($repoPath, 0755, true);

        (new Process(['git', 'init'], $repoPath))->run();
        file_put_contents($repoPath.DIRECTORY_SEPARATOR.'readme.txt', 'hello');

        $ctx = context(workingDirectory: $repoPath);
        $action = new class(message: 'no identity', stageAll: true) extends GitCommit
        {
            #[Override]
            protected function gitConfig(string $git, string $key, ?string $cwd): string
            {
                return '';
            }
        };

        $result = $action->execute($ctx);

        expect($result->successful)->toBeTrue();
        expect($action->didCreateCommit())->toBeTrue();
    });

});
