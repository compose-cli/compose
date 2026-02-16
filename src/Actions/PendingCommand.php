<?php

namespace Compose\Actions;

use Closure;
use Stringable;

class PendingCommand implements Stringable
{
    /**
     * The base parts of the command (binary + subcommand).
     *
     * @var string[]
     */
    protected array $parts = [];

    /**
     * The flags to include in the command.
     *
     * @var string[]
     */
    protected array $flags = [];

    /**
     * The arguments to include in the command.
     *
     * @var string[]
     */
    protected array $arguments = [];

    public function __construct(string ...$parts)
    {
        $this->parts = $parts;
    }

    /**
     * Add one or more arguments to the command.
     */
    public function argument(string ...$args): static
    {
        array_push($this->arguments, ...$args);

        return $this;
    }

    /**
     * Add a flag to the command.
     */
    public function flag(string $flag): static
    {
        $this->flags[] = $flag;

        return $this;
    }

    /**
     * Conditionally modify the command.
     */
    public function when(bool $condition, Closure $callback): static
    {
        if ($condition) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Resolve the command to an array suitable for symfony/process.
     *
     * @return string[]
     */
    public function toArray(): array
    {
        return array_merge($this->parts, $this->flags, $this->arguments);
    }

    /**
     * Resolve the command to a human-readable string.
     */
    public function toString(): string
    {
        return implode(' ', $this->toArray());
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
