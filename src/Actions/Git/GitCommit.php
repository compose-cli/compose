<?php

declare(strict_types=1);

namespace Compose\Actions\Git;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\GitOperation;
use Compose\Execution\ActionResult;
use Compose\RecipeContext;
use Symfony\Component\Process\Process;

/**
 * Stages (optional) and creates a git commit that can be rolled back.
 *
 * This action creates Process instances directly rather than going through
 * ProcessExecutor. As a result, ProcessExecutor::fake() will not intercept
 * its commands. Tests for this action should use real git repos in temp
 * directories instead of relying on the fake executor.
 *
 * Rollback uses `git reset --mixed` to the pre-commit SHA (or deletes HEAD
 * for the repository's first commit), leaving the working tree intact so
 * action-level rollbacks can still undo packages and files.
 */
class GitCommit extends Action
{
    protected ?string $parentSha = null;

    protected bool $createdCommit = false;

    public function __construct(
        public readonly ?string $message = null,
        public readonly bool $stageAll = true,
    ) {}

    #[\Override]
    public function type(): GitOperation
    {
        return GitOperation::Commit;
    }

    #[\Override]
    public function defaultTimeout(): float
    {
        return 30.0;
    }

    #[\Override]
    public function isDirect(): bool
    {
        return true;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        return $this->git('commit')
            ->flag('-m')
            ->argument($this->effectiveMessage());
    }

    #[\Override]
    public function describe(): string
    {
        return $this->command()->toString();
    }

    #[\Override]
    public function execute(RecipeContext $context): ActionResult
    {
        $cwd = $context->workingDirectory;
        $git = $context->gitBinary;
        $message = $this->effectiveMessage();

        $this->parentSha = $this->resolveHeadSha($git, $cwd);
        $this->createdCommit = false;

        if ($this->stageAll) {
            $add = new Process([$git, 'add', '-A'], $cwd);
            $add->setTimeout($this->defaultTimeout());
            $add->run();

            if (! $add->isSuccessful()) {
                return new ActionResult(
                    command: [$git, 'add', '-A'],
                    exitCode: $add->getExitCode() ?? 1,
                    output: $add->getOutput(),
                    errorOutput: $add->getErrorOutput(),
                    successful: false,
                );
            }
        }

        if (! $this->hasChangesToCommit($git, $cwd)) {
            return ActionResult::success(
                command: [$git, 'commit', '-m', $message],
                output: 'nothing to commit, working tree clean',
            );
        }

        $command = $this->buildCommitCommand($git, $message, $cwd);
        $process = new Process($command, $cwd);
        $process->setTimeout($this->defaultTimeout());
        $process->run();

        if ($process->isSuccessful()) {
            $this->createdCommit = true;
        }

        return new ActionResult(
            command: [$git, 'commit', '-m', $message],
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
            successful: $process->isSuccessful(),
        );
    }

    #[\Override]
    public function canRollbackDirect(): bool
    {
        return $this->createdCommit;
    }

    #[\Override]
    public function rollbackDirect(RecipeContext $context): ?ActionResult
    {
        if (! $this->createdCommit) {
            return null;
        }

        $cwd = $context->workingDirectory;
        $git = $context->gitBinary;

        if ($this->parentSha !== null) {
            $command = [$git, 'reset', '--mixed', $this->parentSha];
            $process = new Process($command, $cwd);
            $process->run();

            if ($process->isSuccessful()) {
                $this->createdCommit = false;
            }

            return new ActionResult(
                command: $command,
                exitCode: $process->getExitCode() ?? 1,
                output: $process->getOutput(),
                errorOutput: $process->getErrorOutput(),
                successful: $process->isSuccessful(),
            );
        }

        // First commit on an unborn branch — delete HEAD to return to unborn state.
        $command = [$git, 'update-ref', '-d', 'HEAD'];
        $process = new Process($command, $cwd);
        $process->run();

        if ($process->isSuccessful()) {
            $this->createdCommit = false;
        }

        return new ActionResult(
            command: $command,
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
            successful: $process->isSuccessful(),
        );
    }

    /**
     * Whether this commit actually created a git commit object.
     */
    public function didCreateCommit(): bool
    {
        return $this->createdCommit;
    }

    /**
     * The HEAD SHA captured before the commit, or null for an unborn branch.
     */
    public function parentSha(): ?string
    {
        return $this->parentSha;
    }

    protected function effectiveMessage(): string
    {
        $message = trim($this->message ?? '');

        return $message !== '' ? $message : 'compose: changes';
    }

    protected function resolveHeadSha(string $git, ?string $cwd): ?string
    {
        $process = new Process([$git, 'rev-parse', 'HEAD'], $cwd);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $sha = trim($process->getOutput());

        return $sha !== '' ? $sha : null;
    }

    protected function hasChangesToCommit(string $git, ?string $cwd): bool
    {
        $process = new Process([$git, 'status', '--porcelain'], $cwd);
        $process->run();

        return trim($process->getOutput()) !== '';
    }

    /**
     * @return list<string>
     */
    protected function buildCommitCommand(string $git, string $message, ?string $cwd): array
    {
        $command = [$git];

        // Avoid flaky failures in environments without user.name / user.email.
        if ($this->gitConfig($git, 'user.name', $cwd) === '') {
            $command = [...$command, '-c', 'user.name=Compose'];
        }

        if ($this->gitConfig($git, 'user.email', $cwd) === '') {
            $command = [...$command, '-c', 'user.email=compose@localhost'];
        }

        return [...$command, '-c', 'commit.gpgsign=false', 'commit', '--no-gpg-sign', '-m', $message];
    }

    protected function gitConfig(string $git, string $key, ?string $cwd): string
    {
        $process = new Process([$git, 'config', $key], $cwd);
        $process->run();

        if (! $process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }
}
