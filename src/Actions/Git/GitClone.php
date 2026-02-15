<?php

namespace Compose\Actions\Git;

use Compose\Actions\Action;
use Compose\Contracts\Operation;
use Compose\Enums\GitOperation;

class GitClone extends Action
{
    private string $cloneCommand = 'clone %s';

    private string $cloneWithBranchCommand = 'clone --branch %s %s';

    public function __construct(
        public readonly string $repo,
        public readonly string|null $branch = null,
        public readonly string|null $bin = null,
    ) {}

    public function type(): GitOperation
    {
        return GitOperation::Clone;
    }

    public function getCommand(): string
    {
        $args = $this->branch
            ? sprintf($this->cloneWithBranchCommand, $this->getEscapedBranch(), $this->getEscapedRepo())
            : sprintf($this->cloneCommand, $this->getEscapedRepo());

        return $this->bin . ' ' . $args;
    }

    private function getEscapedRepo(): string
    {
        return escapeshellarg($this->repo);
    }

    private function getEscapedBranch(): string
    {
        return escapeshellarg($this->branch);
    }
}