<?php

namespace Compose\Execution;

use Closure;

class Pipeline
{
    protected mixed $passable = null;

    /** @var array<class-string|object> */
    protected array $pipes = [];

    /**
     * Set the object being sent through the pipeline.
     */
    public function send(mixed $passable): static
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * Set the pipes to send the passable through.
     *
     * @param  array<class-string|object>  $pipes
     */
    public function through(array $pipes): static
    {
        $this->pipes = $pipes;

        return $this;
    }

    /**
     * Run the pipeline and return the result.
     */
    public function thenReturn(): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            fn (mixed $passable) => $passable,
        );

        return $pipeline($this->passable);
    }

    /**
     * Get a Closure that represents a slice of the pipeline.
     */
    protected function carry(): Closure
    {
        return fn (Closure $next, mixed $pipe): Closure => function (mixed $passable) use ($next, $pipe) {
            $instance = is_string($pipe) ? new $pipe : $pipe;

            return $instance->handle($passable, $next);
        };
    }
}
