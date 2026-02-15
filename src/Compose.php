<?php

namespace Compose;

use Compose\Actions\Git\GitClone;
use Compose\Enums\Node;
use Compose\Contracts\AI;
use Compose\Enums\Anthropic;
use Compose\Enums\TaskType;

class Compose
{
    /**
     * The target directory to compose in.
     */
    protected ?string $target = null;

    /**
     * Whether to create a wipe-and-replace directory.
     * 
     * If false, and the directory exists, we will throw an exception. 
     * 
     * Otherwise, we will wipe the directory and start fresh.
     */
    protected bool $fresh = false;

    /**
     * The base repository to use for the composition.
     * 
     * If not provided, we won't use a base repository, just work in the target directory.
     */
    public ?string $baseRepo = null;

    /**
     * The base branch to use for the base repository.
     * 
     * If not provided, the default branch of the repository will be used.
     */
    public ?string $baseBranch = null;

    /**
     * Whether to commit automatically.
     */
    protected bool $commitAutomatically = true;

    /**
     * Whether to use AI generated commit messages.
     */
    protected bool $commitUsingAI = false;

    /**
     * The default AI provider to use.
     */
    protected string|null $aiProvider = null;

    /**
     * The default AI model to use.
     */
    protected AI|string|null $aiModel = null;

    /**
     * The default node package manager to use.
     */
    protected Node|string $nodePackageManager = Node::Npm;

    /**
     * The composer binary to use.
     */
    protected string|null $composerBinary = 'composer';

    /**
     * The git binary to use.
     */
    protected string|null $gitBinary = 'git';

    /**
     * The before callbacks to run before the composition.
     * 
     * @var callable[]
     */
    protected array $beforeCallbacks = [];

    /**
     * The after callbacks to run after the composition.
     * 
     * @var callable[]
     */
    protected array $afterCallbacks = [];

    public function __construct(
        protected ?string $name = null,
        protected TaskType|string $type = TaskType::NewProject
    ) {
        $this->name = $name ?? 'default';
    }

    public function in(string $target = '.', bool $fresh = false): static
    {
        $this->target = $target;
        $this->fresh = $fresh;

        return $this;
    }

    public function base(string $repo, ?string $branch = null): static
    {
        $this->baseRepo = $repo;
        $this->baseBranch = $branch;

        return $this;
    }

    public function commit(bool $automatically = true, bool $smart = false): static
    {
        $this->commitAutomatically = $automatically;
        $this->commitUsingAI = $smart;

        return $this;
    }

    public function ai(AI $ai): static
    {
        $this->aiProvider = $ai->provider();
        $this->aiModel = $ai->value;

        return $this;
    }

    public function node(Node|string $manager): static
    {
        $this->nodePackageManager = $manager;

        return $this;
    }

    public function composer(string|null $bin = 'composer'): static
    {
        $this->composerBinary = $bin;

        return $this;
    }

    public function git(string|null $bin = 'git'): static
    {
        $this->gitBinary = $bin;

        return $this;
    }

    public function before(callable $callback): static
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    public function after(callable $callback): static
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    public function step(string $name, callable $callback, ?string $description = null, ?string $message = null): Step
    {
        return new Step($this, $name, $description, $callback, $message);
    }

    public function isUsingAI(): bool
    {
        return $this->aiProvider !== null && $this->aiModel !== null;
    }

    public function getNodeManager(): Node
    {
        return $this->nodePackageManager;
    }

    public function getNodeBinary(): string
    {
        return $this->nodePackageManager->value;
    }

    public function getComposerBinary(): string|null
    {
        return $this->composerBinary;
    }

    public function getGitBinary(): string|null
    {
        return $this->gitBinary;
    }
}