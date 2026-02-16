<?php

namespace Compose\Actions\Git;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\Enums\GitOperation;

class GitClone extends Action
{
    public function __construct(
        public readonly string $repo,
        public readonly ?string $branch = null,
        public readonly ?string $directory = null,
    ) {}

    public function type(): GitOperation
    {
        return GitOperation::Clone;
    }

    public function command(): PendingCommand
    {
        $cmd = $this->git('clone')
            ->when($this->branch !== null, fn (PendingCommand $cmd) => $cmd
                ->flag('--branch')
                ->argument($this->branch)
            )
            ->argument($this->repo);

        if ($this->directory !== null) {
            $cmd->argument($this->directory);
        }

        return $cmd;
    }

    /**
     * Get the directory name that this clone creates.
     */
    public function targetDirectory(): string
    {
        return $this->directory ?? basename($this->repo, '.git');
    }

    public function rollback(): PendingCommand
    {
        return PHP_OS_FAMILY === 'Windows'
            ? new PendingCommand('cmd', '/c', 'rmdir', '/s', '/q', $this->targetDirectory())
            : new PendingCommand('rm', '-rf', $this->targetDirectory());
    }
}
