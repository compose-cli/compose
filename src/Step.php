<?php

declare(strict_types=1);

namespace Compose;

use Closure;
use Compose\Actions\Action;
use Compose\Actions\AI\InstructAction;
use Compose\Actions\Artisan\ArtisanAction;
use Compose\Actions\Composer\ComposerInstall;
use Compose\Actions\Composer\ComposerRemove;
use Compose\Actions\Composer\ComposerRun;
use Compose\Actions\Env\EnvAction;
use Compose\Actions\File\AppendFile;
use Compose\Actions\File\CopyFile;
use Compose\Actions\File\CreateFile;
use Compose\Actions\File\DeleteFile;
use Compose\Actions\File\ReadFile;
use Compose\Actions\Git\GitAdd;
use Compose\Actions\Git\GitCommit;
use Compose\Actions\Modify\ModifyAction;
use Compose\Actions\Node\NodeInstall;
use Compose\Actions\Node\NodeRemove;
use Compose\Actions\Node\NodeRun;
use Compose\Actions\Sink;
use Compose\Builders\Artisan;
use Compose\Builders\EnvBuilder;
use Compose\Builders\InstructBuilder;
use Compose\Builders\ModifyBuilder;
use Compose\Enums\FailureStrategy;

class Step
{
    /**
     * @var Action[]
     */
    protected array $operations = [];

    protected bool $resolved = false;

    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?Closure $callback = null,
        public readonly ?string $message = null,
        public readonly FailureStrategy $failureStrategy = FailureStrategy::Abort,
        public readonly ?float $timeout = null,
    ) {}

    public function composer(
        array|string|null $install = null,
        array|string|null $dev = null,
        array|string|null $remove = null,
        array|string|null $removeDev = null,
        ?string $run = null,
        array|string|null $args = null,
        bool $allowFailure = false,
    ): static {
        if ($install !== null) {
            $action = new ComposerInstall($install, dev: false);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        if ($dev !== null) {
            $action = new ComposerInstall($dev, dev: true);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        if ($remove !== null) {
            $action = new ComposerRemove($remove, dev: false);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        if ($removeDev !== null) {
            $action = new ComposerRemove($removeDev, dev: true);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        if ($run !== null) {
            $action = new ComposerRun(script: $run, args: $args ?? []);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        return $this;
    }

    public function node(
        array|string|null $install = null,
        array|string|null $dev = null,
        array|string|null $remove = null,
        array|string|null $removeDev = null,
        ?string $run = null,
        array|string|null $args = null,
        bool $allowFailure = false,
    ): static {
        if ($install !== null) {
            $action = new NodeInstall($install, dev: false);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        if ($dev !== null) {
            $action = new NodeInstall($dev, dev: true);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        if ($remove !== null) {
            $action = new NodeRemove($remove, dev: false);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        if ($removeDev !== null) {
            $action = new NodeRemove($removeDev, dev: true);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        if ($run !== null) {
            $action = new NodeRun(script: $run, args: $args ?? []);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        return $this;
    }

    /**
     * Add artisan operations to this step.
     *
     * When passed a string, creates a single artisan command.
     * When passed a closure, receives an ArtisanBuilder for batch operations.
     */
    public function artisan(string|Closure $command): static
    {
        if (is_string($command)) {
            $this->operations[] = new ArtisanAction($command);

            return $this;
        }

        $builder = new Artisan;

        $command($builder);

        foreach ($builder->actions() as $action) {
            $this->operations[] = $action;
        }

        return $this;
    }

    /**
     * Fetch a remote file and write it to the project.
     *
     * Supports raw URLs and GitHub shorthand:
     *   github:owner/repo@ref:path/to/file
     *   github:owner/repo:path/to/file (ref defaults to main)
     *
     * When $to is null, the target path is derived from the source.
     */
    public function sink(string $from, ?string $to = null, bool $force = true): static
    {
        $this->operations[] = new Sink($from, $to, $force);

        return $this;
    }

    /**
     * Create a file with the given contents.
     *
     * The path is relative to the working directory. Parent directories
     * are created automatically. Existing files are overwritten unless
     * force is set to false.
     */
    public function create(string $path, string $contents, bool $overwrite = true): static
    {
        $this->operations[] = new CreateFile(
            path: $path,
            contents: $contents,
            overwrite: $overwrite,
        );

        return $this;
    }

    /**
     * Read a file's contents.
     *
     * The file contents are captured in the ActionResult output,
     * making them available to downstream actions and AI context.
     */
    public function read(string $path): static
    {
        $this->operations[] = new ReadFile(path: $path);

        return $this;
    }

    /**
     * Copy a file from one path to another.
     *
     * Both paths are relative to the working directory. Parent directories
     * for the target are created automatically. Existing targets are
     * overwritten unless overwrite is set to false.
     */
    public function copy(string $from, string $to, bool $overwrite = true): static
    {
        $this->operations[] = new CopyFile(
            from: $from,
            to: $to,
            overwrite: $overwrite,
        );

        return $this;
    }

    /**
     * Append content to an existing file.
     *
     * Fails if the target file does not exist. The appended bytes
     * are tracked for rollback via truncation.
     */
    public function append(string $path, string $contents): static
    {
        $this->operations[] = new AppendFile(
            path: $path,
            contents: $contents,
        );

        return $this;
    }

    /**
     * Delete one or more files or directories.
     *
     * Supports glob patterns. Directories are deleted recursively.
     * Missing files are silently skipped.
     */
    public function delete(string ...$paths): static
    {
        $this->operations[] = new DeleteFile(...$paths);

        return $this;
    }

    /**
     * Manipulate an environment file.
     *
     * When passed an array, sets each key-value pair (bulk mode).
     * When passed a closure, receives an EnvBuilder for fine-grained
     * operations (set, remove, comment, uncomment, section, when).
     */
    public function env(array|Closure $values, string $path = '.env'): static
    {
        $builder = new EnvBuilder;

        if (is_array($values)) {
            $builder->merge($values);
        } else {
            $values($builder);
        }

        $this->operations[] = new EnvAction(path: $path, operations: $builder->operations());

        return $this;
    }

    /**
     * Modify an existing file.
     *
     * Uses Nette PHP Generator for AST-safe class manipulation on .php files,
     * JsonManipulator for .json files, and TextManipulator for everything else.
     *
     * @param  Closure(ModifyBuilder): void  $callback
     */
    public function modify(string $file, Closure $callback): static
    {
        $builder = new ModifyBuilder;
        $callback($builder);

        $this->operations[] = new ModifyAction(path: $file, operations: $builder->operations());

        return $this;
    }

    /**
     * Delegate a task to an AI CLI tool.
     *
     * The AI runs in the working directory with full filesystem access.
     * Compose captures changes via git for rollback and optional patch caching.
     *
     * @param  Closure(InstructBuilder): void|null  $callback
     */
    public function instruct(string $description, ?Closure $callback = null): static
    {
        $builder = new InstructBuilder;

        if ($callback !== null) {
            $callback($builder);
        }

        $this->operations[] = new InstructAction($builder->toPayload($description));

        return $this;
    }

    /**
     * Add a git commit to this step.
     *
     * When message is null, the commit message will be resolved
     * later by the CommitMessageGenerator (AI or default).
     */
    public function commit(?string $message = null): static
    {
        $this->operations[] = new GitAdd;
        $this->operations[] = new GitCommit(message: $message);

        return $this;
    }

    /**
     * Add an operation directly to this step.
     */
    public function addOperation(Action $action): static
    {
        $this->operations[] = $action;

        return $this;
    }

    /**
     * Execute the callback when the condition is truthy.
     *
     * Accepts a boolean or a Closure that returns a boolean.
     * Closure conditions are evaluated at call time (during
     * operation resolution), enabling deferred checks.
     *
     * @param  Closure(static): void  $callback
     */
    public function when(Closure|bool $condition, Closure $callback): static
    {
        $result = $condition instanceof Closure ? $condition() : $condition;

        if ($result) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Execute the callback when the condition is falsy.
     *
     * @param  Closure(static): void  $callback
     */
    public function unless(Closure|bool $condition, Closure $callback): static
    {
        $result = $condition instanceof Closure ? $condition() : $condition;

        if (! $result) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Execute a callback for side effects without affecting the chain.
     *
     * @param  Closure(static): void  $callback
     */
    public function tap(Closure $callback): static
    {
        $callback($this);

        return $this;
    }

    /**
     * Assert that a condition holds, stopping execution if it fails.
     *
     * The closure is called at execution time. A falsy return value
     * causes the step (and recipe) to fail immediately.
     */
    public function assert(Closure $assertion): static
    {
        $this->operations[] = new Actions\Verify\VerifyAction($assertion);

        return $this;
    }

    /**
     * Verify a condition or AI assertion, logging a warning on failure.
     *
     * When passed a Closure, it is called at execution time and checked
     * for truthiness. When passed a string, it is deferred to AI
     * verification (placeholder — currently skipped).
     */
    public function verify(string|Closure $assertion): static
    {
        $this->operations[] = new Actions\Verify\VerifyAction($assertion, allowFailure: true);

        return $this;
    }

    /**
     * Run one or more test files via artisan test.
     *
     * Each path creates a separate TestAction. Failures are
     * treated as warnings so the recipe can continue.
     */
    public function test(array|string $tests, bool $allowFailure = true): static
    {
        foreach ((array) $tests as $test) {
            $this->operations[] = new Actions\Test\TestAction($test, allowFailure: $allowFailure);
        }

        return $this;
    }

    /**
     * Whether a failed action should be treated as a warning.
     */
    public function shouldWarnOnFailure(Action $action): bool
    {
        return $action->allowFailure || $this->failureStrategy === FailureStrategy::Continue;
    }

    /**
     * Resolve the step's operations by calling its callback.
     *
     * This is the first phase of the two-phase execution model.
     * The callback populates the operations array, which is then
     * iterated by the Runner in the second (execution) phase.
     */
    public function resolveOperations(): void
    {
        if ($this->resolved) {
            return;
        }

        if ($this->callback !== null) {
            call_user_func($this->callback, $this);
        }

        $this->resolved = true;
    }

    /**
     * @return Action[]
     */
    public function operations(): array
    {
        if (! $this->resolved) {
            $this->resolveOperations();
        }

        return $this->operations;
    }
}
