<?php

namespace Compose\Actions\Composer;

use Compose\Actions\Action;
use Compose\Enums\PackageOperation;

class ComposerRun extends Action
{
    public function __construct(
        public readonly string $script,
        public readonly array|string $args = [],
        protected readonly string|null $bin = null,
    ) {}

    public function type(): PackageOperation
    {
        return PackageOperation::Run;
    }

    public function getCommand(): string
    {
        $command = $this->bin . ' run ' . escapeshellarg($this->script);

        $escapedArgs = $this->getEscapedArgs();

        if ($escapedArgs !== '') {
            $command .= ' -- ' . $escapedArgs;
        }

        return $command;
    }

    protected function getEscapedArgs(): string
    {
        if (is_string($this->args)) {
            return escapeshellarg($this->args);
        }

        if (empty($this->args)) {
            return '';
        }

        return implode(' ', array_map(fn($arg) => escapeshellarg($arg), $this->args));
    }
}
