<?php

namespace Compose\Events;

use Closure;

class EventDispatcher
{
    /**
     * @var array<class-string, Closure[]>
     */
    protected array $listeners = [];

    /**
     * Register a listener for an event.
     */
    public function listen(string $event, Closure $listener): static
    {
        $this->listeners[$event][] = $listener;

        return $this;
    }

    /**
     * Dispatch an event to all registered listeners.
     */
    public function dispatch(object $event): void
    {
        $listeners = $this->listeners[$event::class] ?? [];

        foreach ($listeners as $listener) {
            $listener($event);
        }
    }
}
