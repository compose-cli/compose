<?php

declare(strict_types=1);

namespace Compose;

use Closure;
use Compose\Actions\Git\GitBranch;
use Compose\Actions\Git\GitCheckout;
use Compose\Actions\Git\GitClone;
use Compose\Contracts\AI;
use Compose\Enums\FailureStrategy;
use Compose\Enums\Node;
use Compose\Enums\TaskType;
use Compose\Events\EventDispatcher;
use Compose\Execution\Plan;
use Compose\Execution\ProcessExecutor;
use Compose\Execution\RecipeConfig;
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
    protected bool $commitAutomatically = false;

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
        if ($fresh && $this->resolvedType() !== TaskType::NewProject) {
            throw new \LogicException('fresh mode can only be used with TaskType::NewProject.');
        }

        $this->target = $target;
        $this->fresh = $fresh;

        return $this;
    }

    public function inCwd(): static
    {
        return $this->in((string) getcwd());
    }

    public function base(string $repo, ?string $branch = null): static
    {
        if ($this->resolvedType() !== TaskType::NewProject) {
            throw new \LogicException('base() can only be used with TaskType::NewProject. Use branch() for existing projects.');
        }

        $this->baseRepo = $repo;
        $this->baseBranch = $branch;
        $this->projectName = slugify($this->getName());

        $directory = $this->projectName;

        array_unshift($this->steps, new Step(
            name: 'Clone base repository',
            description: "Clone {$repo}".($branch ? " (branch: {$branch})" : '')." into {$directory}",
            callback: function (Step $step) use ($repo, $branch, $directory): void {
                $step->addOperation(new GitClone(repo: $repo, branch: $branch, directory: $directory));
            },
        ));

        return $this;
    }

    public function branch(string $name, bool $create = true): static
    {
        if ($this->resolvedType() === TaskType::NewProject) {
            throw new \LogicException('branch() is for existing projects. Use base() for new projects.');
        }

        array_unshift($this->steps, new Step(
            name: 'Switch to branch',
            description: ($create ? 'Create and checkout' : 'Checkout')." branch {$name}",
            callback: function (Step $step) use ($name, $create): void {
                $step->addOperation(
                    $create ? new GitBranch(branch: $name) : new GitCheckout(branch: $name),
                );
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

    public function step(string $name, Closure $operations, ?string $description = null, ?string $message = null, FailureStrategy $onFailure = FailureStrategy::Abort): static
    {
        $step = new Step($name, $description, $operations, $message, $onFailure);

        $this->steps[] = $step;

        return $this;
    }

    /**
     * Execute the composition and return the result.
     */
    public function run(?EventDispatcher $dispatcher = null): RunResult
    {
        $runner = new Runner(new ProcessExecutor, $dispatcher ?? new EventDispatcher);

        return $runner->run($this->toConfig());
    }

    /**
     * Plan the composition without executing anything.
     */
    public function plan(): Plan
    {
        $runner = new Runner(new ProcessExecutor, new EventDispatcher);

        return $runner->plan($this->toConfig());
    }

    /**
     * Build an immutable RecipeConfig from the current state.
     */
    public function toConfig(): RecipeConfig
    {
        return new RecipeConfig(
            name: $this->getName(),
            taskType: $this->resolvedType(),
            context: $this->getContext(),
            baseContext: $this->baseRepo !== null ? $this->getBaseContext() : null,
            fresh: $this->fresh,
            autoCommit: $this->commitAutomatically,
            smartCommit: $this->commitUsingAI,
            steps: $this->steps,
            beforeCallbacks: $this->beforeCallbacks,
            afterCallbacks: $this->afterCallbacks,
        );
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

    /**
     * Load a Compose instance from a recipe file.
     */
    public static function fromFile(string $path): static
    {
        if (! file_exists($path)) {
            throw new \RuntimeException("Recipe file not found: {$path}");
        }

        $compose = require $path;

        if (! $compose instanceof static) {
            throw new \RuntimeException('The recipe must return a Compose object.');
        }

        return $compose;
    }

    // ------------------------------------------------------------------
    // Getters
    // ------------------------------------------------------------------

    public function getName(): string
    {
        return $this->name ?? 'default';
    }

    public function getTaskType(): TaskType
    {
        return $this->resolvedType();
    }

    protected function resolvedType(): TaskType
    {
        return $this->type instanceof TaskType
            ? $this->type
            : TaskType::from($this->type);
    }

    public function getProjectName(): ?string
    {
        return $this->projectName;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function isUsingAI(): bool
    {
        return $this->aiProvider !== null && $this->aiModel !== null;
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
