<?php

namespace Compose;

use Closure;
use Compose\Actions\Git\GitClone;
use Compose\Contracts\AI;
use Compose\Enums\Node;
use Compose\Enums\TaskType;
use Compose\Events\EventDispatcher;
use Compose\Execution\Plan;
use Compose\Execution\ProcessExecutor;
use Compose\Execution\Runner;
use Compose\Execution\RunResult;

class Compose
{
    /**
     * The target directory to compose in.
     */
    protected ?string $target = null;

    /**
     * Whether to create a wipe-and-replace directory.
     */
    protected bool $fresh = false;

    /**
     * The base repository to use for the composition.
     */
    protected ?string $baseRepo = null;

    /**
     * The base branch to use for the base repository.
     */
    protected ?string $baseBranch = null;

    /**
     * The slugified project directory name (derived from the recipe name).
     */
    protected ?string $projectName = null;

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
    protected ?string $aiProvider = null;

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
    protected string $composerBinary = 'composer';

    /**
     * The git binary to use.
     */
    protected string $gitBinary = 'git';

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

    /**
     * The steps to run during the composition.
     *
     * @var Step[]
     */
    protected array $steps = [];

    public function __construct(
        protected ?string $name = null,
        protected TaskType|string $type = TaskType::NewProject,
    ) {}

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
        $this->projectName = slugify($this->getName());

        $directory = $this->projectName;

        array_unshift($this->steps, new Step(
            context: $this->getBaseContext(),
            name: 'Clone base repository',
            description: "Clone {$repo}".($branch ? " (branch: {$branch})" : '')." into {$directory}",
            callback: function (Step $step) use ($repo, $branch, $directory): void {
                $step->addOperation(new GitClone(repo: $repo, branch: $branch, directory: $directory));
            },
        ));

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

    public function composer(string $bin): static
    {
        $this->composerBinary = $bin;

        return $this;
    }

    public function git(string $bin): static
    {
        $this->gitBinary = $bin;

        return $this;
    }

    public function before(Closure $callback): static
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    public function after(Closure $callback): static
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    public function step(string $name, Closure $callback, ?string $description = null, ?string $message = null): Step
    {
        $step = new Step($this->getContext(), $name, $description, $callback, $message);

        $this->steps[] = $step;

        return $step;
    }

    /**
     * Execute the composition and return the result.
     */
    public function compose(?EventDispatcher $dispatcher = null): RunResult
    {
        $runner = new Runner(new ProcessExecutor, $dispatcher ?? new EventDispatcher);

        return $runner->run($this);
    }

    /**
     * Plan the composition without executing anything.
     */
    public function plan(): Plan
    {
        $runner = new Runner(new ProcessExecutor, new EventDispatcher);

        return $runner->plan($this);
    }

    /**
     * Build a RecipeContext from the current configuration.
     *
     * When a base repository is configured, the working directory
     * is set to the project subdirectory (target/projectName) so
     * that subsequent steps run inside the cloned project.
     */
    public function getContext(): RecipeContext
    {
        $nodeManager = $this->nodePackageManager instanceof Node
            ? $this->nodePackageManager
            : Node::from($this->nodePackageManager);

        $workingDirectory = $this->target;

        if ($this->projectName !== null && $this->target !== null) {
            $workingDirectory = rtrim($this->target, '/\\').DIRECTORY_SEPARATOR.$this->projectName;
        }

        return new RecipeContext(
            composerBinary: $this->composerBinary,
            gitBinary: $this->gitBinary,
            nodeManager: $nodeManager,
            workingDirectory: $workingDirectory,
        );
    }

    /**
     * Build a RecipeContext for the base clone step.
     *
     * Uses the raw target directory (not the project subdirectory)
     * so git clone runs in the parent directory.
     */
    public function getBaseContext(): RecipeContext
    {
        $nodeManager = $this->nodePackageManager instanceof Node
            ? $this->nodePackageManager
            : Node::from($this->nodePackageManager);

        return new RecipeContext(
            composerBinary: $this->composerBinary,
            gitBinary: $this->gitBinary,
            nodeManager: $nodeManager,
            workingDirectory: $this->target,
        );
    }

    // ------------------------------------------------------------------
    // Getters for the Runner
    // ------------------------------------------------------------------

    public function getName(): string
    {
        return $this->name ?? 'default';
    }

    public function getProjectName(): ?string
    {
        return $this->projectName;
    }

    public function getBaseRepo(): ?string
    {
        return $this->baseRepo;
    }

    public function getBaseBranch(): ?string
    {
        return $this->baseBranch;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function isFresh(): bool
    {
        return $this->fresh;
    }

    public function shouldAutoCommit(): bool
    {
        return $this->commitAutomatically;
    }

    public function shouldUseSmartCommit(): bool
    {
        return $this->commitUsingAI;
    }

    public function isUsingAI(): bool
    {
        return $this->aiProvider !== null && $this->aiModel !== null;
    }

    /**
     * @return Step[]
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * @return callable[]
     */
    public function getBeforeCallbacks(): array
    {
        return $this->beforeCallbacks;
    }

    /**
     * @return callable[]
     */
    public function getAfterCallbacks(): array
    {
        return $this->afterCallbacks;
    }

    public function getNodeManager(): Node
    {
        return $this->nodePackageManager instanceof Node
            ? $this->nodePackageManager
            : Node::from($this->nodePackageManager);
    }

    public function getNodeBinary(): string
    {
        return $this->getNodeManager()->value;
    }

    public function getComposerBinary(): string
    {
        return $this->composerBinary;
    }

    public function getGitBinary(): string
    {
        return $this->gitBinary;
    }
}
