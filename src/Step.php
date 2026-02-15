<?php

namespace Compose;

use Compose\Actions\Composer\ComposerInstall;
use Compose\Actions\Composer\ComposerRemove;

class Step
{
    protected array $operations = [];

    public function __construct(
        protected readonly Compose $recipe,
        protected readonly string $name,
        protected readonly ?string $description = null,
        protected readonly callable $callback,
        protected string|null $message = null,
    ) {
        //
    }

    public function composer(
        array|string|null $install = null,
        array|string|null $dev = null,
        array|string|null $remove = null,
        array|string|null $removeDev = null,
        array|null $scripts = null,
        string|null $run = null,
        array|string|null $args = null,
    ): static {
        /**
         * All these operations will be deferred until after we make sure we have a composer.json file.
         */
        $operations = [];

        if ($install !== null) {
            $operations[] = new ComposerInstall($install, dev: false, bin: $this->recipe->getComposerBinary());
        }

        if ($dev !== null) {
            $operations[] = new ComposerInstall($dev, dev: true, bin: $this->recipe->getComposerBinary());
        }

        if ($remove !== null) {
            $operations[] = new ComposerRemove($remove, dev: false, bin: $this->recipe->getComposerBinary());
        }

        if ($removeDev !== null) {
            $operations[] = new ComposerRemove($removeDev, dev: true, bin: $this->recipe->getComposerBinary());
        }

        // if ($scripts !== null) {
        //     $operations[] = new ComposerScripts($scripts);
        // }

        // if ($run !== null) {
        //     $operations[] = new ComposerRun(script: $run, args: $args ?? [], bin: $this->recipe->getComposerBinary());
        // }

        $this->operations = array_merge($this->operations, $operations);

        return $this;
    }
}
