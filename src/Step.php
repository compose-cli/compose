<?php

namespace Compose;

use Closure;
use Compose\Actions\Action;
use Compose\Actions\Composer\ComposerInstall;
use Compose\Actions\Composer\ComposerRemove;
use Compose\Actions\Composer\ComposerRun;
use Compose\Actions\Git\GitAdd;
use Compose\Actions\Git\GitCommit;
use Compose\Actions\Node\NodeInstall;
use Compose\Actions\Node\NodeRemove;
use Compose\Actions\Node\NodeRun;
use Compose\Enums\FailureStrategy;

class Step
{
    /**
     * @var Action[]
     */
    protected array $operations = [];

    protected bool $resolved = false;

    public function __construct(
        protected readonly RecipeContext $context,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?Closure $callback = null,
        public ?string $message = null,
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
        $manager = $this->context->nodeManager;

        if ($install !== null) {
            $action = new NodeInstall($install, dev: false, manager: $manager);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        if ($dev !== null) {
            $action = new NodeInstall($dev, dev: true, manager: $manager);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        if ($remove !== null) {
            $action = new NodeRemove($remove, dev: false, manager: $manager);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        if ($removeDev !== null) {
            $action = new NodeRemove($removeDev, dev: true, manager: $manager);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

        if ($run !== null) {
            $action = new NodeRun(script: $run, args: $args ?? [], manager: $manager);
            $action->allowFailure = $allowFailure;
            $this->operations[] = $action;
        }

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
