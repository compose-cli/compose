<?php

namespace Compose;

use Compose\Actions\Git\GitClone;
use Compose\Enums\Node;
use Compose\Contracts\AI;
use Compose\Enums\Anthropic;

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
    protected Node|string|null $nodePackageManager = null;

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

    public function step(string $name, callable $callback, ?string $description = null): Step
    {
        return new Step($this, $name, $description, $callback);
    }

    public function isUsingAI(): bool
    {
        return $this->aiProvider !== null && $this->aiModel !== null;
    }

    public function getNodeBinary(): string|null
    {
        return $this->nodePackageManager;
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

$compose = new Compose();

$compose
    ->in('.', fresh: true)
    ->base(repo: 'https://github.com/laravel/laravel.git', branch: '10.x')
    ->commit(automatically: true, smart: true)
    ->ai(Anthropic::ClaudeOpus45)
    ->git('herd git')
    ->node(Node::Yarn)
    ->composer('herd composer');

$compose->before(function (Compose $compose) {
    // need to actually install the base repository using the git clone command
    $action = new GitClone(repo: $compose->baseRepo, branch: $compose->baseBranch, bin: $compose->getGitBinary());
});

$compose->step('Install dependencies', function (Step $step) {
    $step->composer(install: ['laravel/telescope'], remove: ['spatie/ray', 'laravel/horizon']);
});