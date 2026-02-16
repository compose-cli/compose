<?php

namespace Compose;

use Closure;
use Compose\Actions\Action;
use Compose\Actions\Composer\ComposerInstall;
use Compose\Actions\Composer\ComposerRemove;
use Compose\Actions\Composer\ComposerRun;
use Compose\Actions\Node\NodeInstall;
use Compose\Actions\Node\NodeRemove;
use Compose\Actions\Node\NodeRun;

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
    ) {}

    public function composer(
        array|string|null $install = null,
        array|string|null $dev = null,
        array|string|null $remove = null,
        array|string|null $removeDev = null,
        ?array $scripts = null,
        ?string $run = null,
        array|string|null $args = null,
    ): static {
        if ($install !== null) {
            $this->operations[] = new ComposerInstall($install, dev: false);
        }

        if ($dev !== null) {
            $this->operations[] = new ComposerInstall($dev, dev: true);
        }

        if ($remove !== null) {
            $this->operations[] = new ComposerRemove($remove, dev: false);
        }

        if ($removeDev !== null) {
            $this->operations[] = new ComposerRemove($removeDev, dev: true);
        }

        if ($run !== null) {
            $this->operations[] = new ComposerRun(script: $run, args: $args ?? []);
        }

        return $this;
    }

    public function node(
        array|string|null $install = null,
        array|string|null $dev = null,
        array|string|null $remove = null,
        array|string|null $removeDev = null,
        ?array $scripts = null,
        ?string $run = null,
        array|string|null $args = null,
    ): static {
        $manager = $this->context->nodeManager;

        if ($install !== null) {
            $this->operations[] = new NodeInstall($install, dev: false, manager: $manager);
        }

        if ($dev !== null) {
            $this->operations[] = new NodeInstall($dev, dev: true, manager: $manager);
        }

        if ($remove !== null) {
            $this->operations[] = new NodeRemove($remove, dev: false, manager: $manager);
        }

        if ($removeDev !== null) {
            $this->operations[] = new NodeRemove($removeDev, dev: true, manager: $manager);
        }

        if ($run !== null) {
            $this->operations[] = new NodeRun(script: $run, args: $args ?? [], manager: $manager);
        }

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
