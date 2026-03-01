<?php

declare(strict_types=1);

namespace Compose;

use Closure;
use Compose\Actions\Action;
use Compose\Actions\Artisan\ArtisanAction;
use Compose\Actions\Composer\ComposerInstall;
use Compose\Actions\Composer\ComposerRemove;
use Compose\Actions\Composer\ComposerRun;
use Compose\Actions\File\AppendFile;
use Compose\Actions\File\CopyFile;
use Compose\Actions\File\CreateFile;
use Compose\Actions\File\DeleteFile;
use Compose\Actions\File\ReadFile;
use Compose\Actions\Git\GitAdd;
use Compose\Actions\Git\GitCommit;
use Compose\Actions\Node\NodeInstall;
use Compose\Actions\Node\NodeRemove;
use Compose\Actions\Node\NodeRun;
use Compose\Actions\Sink;
use Compose\Builders\Artisan;
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
        return $this->operations;
    }
}
