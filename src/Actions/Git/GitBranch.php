<?php

declare(strict_types=1);

namespace Compose\Actions\Git;

use Compose\Actions\Action;
use Compose\Enums\GitOperation;
use Compose\Execution\ActionResult;
use Compose\RecipeContext;
use Symfony\Component\Process\Process;

/**
 * Creates and checks out a new git branch.
 *
 * This action creates Process instances directly rather than going through
 * ProcessExecutor. As a result, ProcessExecutor::fake() will not intercept
 * its commands. Tests for this action should use real git repos in temp
 * directories instead of relying on the fake executor.
 */
class GitBranch extends Action
{
    protected ?string $originalBranch = null;

    public function __construct(
        public readonly string $branch,
    ) {}

    #[\Override]
    public function type(): GitOperation
    {
        return GitOperation::Branch;
    }

    #[\Override]
    public function isDirect(): bool
    {
        return true;
    }

    #[\Override]
    public function execute(RecipeContext $context): ActionResult
    {
        $cwd = $context->workingDirectory;

        $detect = new Process([$context->gitBinary, 'rev-parse', '--abbrev-ref', 'HEAD'], $cwd);
        $detect->run();

        if ($detect->isSuccessful()) {
            $this->originalBranch = trim($detect->getOutput());
        }

        $process = new Process([$context->gitBinary, 'checkout', '-b', $this->branch], $cwd);
        $process->run();

        return new ActionResult(
            command: [$context->gitBinary, 'checkout', '-b', $this->branch],
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
            successful: $process->isSuccessful(),
        );
    }

    #[\Override]
    public function describe(): string
    {
        return "git checkout -b {$this->branch}";
    }

    #[\Override]
    public function canRollbackDirect(): bool
    {
        return $this->originalBranch !== null;
    }

    #[\Override]
    public function rollbackDirect(RecipeContext $context): ?ActionResult
    {
        if ($this->originalBranch === null) {
            return null;
        }

        $cwd = $context->workingDirectory;

        $checkout = new Process([$context->gitBinary, 'checkout', $this->originalBranch], $cwd);
        $checkout->run();

        if (! $checkout->isSuccessful()) {
            return new ActionResult(
                command: [$context->gitBinary, 'checkout', $this->originalBranch],
                exitCode: $checkout->getExitCode() ?? 1,
                output: $checkout->getOutput(),
                errorOutput: $checkout->getErrorOutput(),
                successful: false,
            );
        }

        $delete = new Process([$context->gitBinary, 'branch', '-D', $this->branch], $cwd);
        $delete->run();

        return new ActionResult(
            command: [$context->gitBinary, 'branch', '-D', $this->branch],
            exitCode: $delete->getExitCode() ?? 1,
            output: $delete->getOutput(),
            errorOutput: $delete->getErrorOutput(),
            successful: $delete->isSuccessful(),
        );
    }
}
