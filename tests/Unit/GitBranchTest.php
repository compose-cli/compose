<?php

use Compose\Actions\Git\GitBranch;
use Compose\Enums\GitOperation;
use Symfony\Component\Process\Process;

describe('GitBranch', function (): void {

    it('has the correct operation type', function (): void {
        $action = new GitBranch(branch: 'feature/auth');

        expect($action->type())->toBe(GitOperation::Branch);
    });

    it('is a direct action', function (): void {
        $action = new GitBranch(branch: 'feature/auth');

        expect($action->isDirect())->toBeTrue();
    });

    it('describes itself as git checkout -b', function (): void {
        $action = new GitBranch(branch: 'feature/permissions');

        expect($action->describe())->toBe('git checkout -b feature/permissions');
    });

    it('cannot be rolled back before execution', function (): void {
        $action = new GitBranch(branch: 'feature/auth');

        expect($action->canRollbackDirect())->toBeFalse();
        expect($action->canBeRolledBack())->toBeFalse();
    });

    it('executes in a real git repo and captures original branch', function (): void {
        $repoPath = $this->tempPath.DIRECTORY_SEPARATOR.'branch-test';
        mkdir($repoPath, 0755, true);

        (new Process(['git', 'init'], $repoPath))->run();
        (new Process(['git', 'commit', '--allow-empty', '-m', 'init'], $repoPath))->run();

        $ctx = context(workingDirectory: $repoPath);
        $action = new GitBranch(branch: 'feature/test');
        $result = $action->execute($ctx);

        expect($result->successful)->toBeTrue();
        expect($action->canRollbackDirect())->toBeTrue();

        $detect = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $repoPath);
        $detect->run();
        expect(trim($detect->getOutput()))->toBe('feature/test');
    });

    it('rolls back to the original branch and deletes the created branch', function (): void {
        $repoPath = $this->tempPath.DIRECTORY_SEPARATOR.'branch-rollback-test';
        mkdir($repoPath, 0755, true);

        (new Process(['git', 'init'], $repoPath))->run();
        (new Process(['git', 'commit', '--allow-empty', '-m', 'init'], $repoPath))->run();

        $detect = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $repoPath);
        $detect->run();
        $originalBranch = trim($detect->getOutput());

        $ctx = context(workingDirectory: $repoPath);
        $action = new GitBranch(branch: 'feature/rollback-test');
        $action->execute($ctx);

        $rollbackResult = $action->rollbackDirect($ctx);

        expect($rollbackResult->successful)->toBeTrue();

        $detect = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $repoPath);
        $detect->run();
        expect(trim($detect->getOutput()))->toBe($originalBranch);

        $branchList = new Process(['git', 'branch', '--list', 'feature/rollback-test'], $repoPath);
        $branchList->run();
        expect(trim($branchList->getOutput()))->toBe('');
    });

    it('returns failure when branch already exists', function (): void {
        $repoPath = $this->tempPath.DIRECTORY_SEPARATOR.'branch-exists-test';
        mkdir($repoPath, 0755, true);

        (new Process(['git', 'init'], $repoPath))->run();
        (new Process(['git', 'commit', '--allow-empty', '-m', 'init'], $repoPath))->run();
        (new Process(['git', 'branch', 'feature/existing'], $repoPath))->run();

        $ctx = context(workingDirectory: $repoPath);
        $action = new GitBranch(branch: 'feature/existing');
        $result = $action->execute($ctx);

        expect($result->successful)->toBeFalse();
    });

});
