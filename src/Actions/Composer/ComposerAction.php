<?php

namespace Compose\Actions\Composer;

use Compose\Actions\Action;

abstract class ComposerAction extends Action
{
    public function __construct(
        public readonly array|string $packages,
        public readonly bool $dev = false,
    ) {}

    /**
     * Get the packages as a flat array.
     *
     * @return string[]
     */
    protected function packageList(): array
    {
        return (array) $this->packages;
    }
}
