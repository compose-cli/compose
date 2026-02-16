<?php

use Compose\Events\EventDispatcher;

describe('EventDispatcher', function (): void {

    it('dispatches events to listeners', function (): void {
        $dispatcher = new EventDispatcher;
        $received = null;

        $dispatcher->listen(TestEvent::class, function (TestEvent $event) use (&$received): void {
            $received = $event->message;
        });

        $dispatcher->dispatch(new TestEvent('hello'));

        expect($received)->toBe('hello');
    });

    it('supports multiple listeners for the same event', function (): void {
        $dispatcher = new EventDispatcher;
        $calls = 0;

        $dispatcher->listen(TestEvent::class, function () use (&$calls): void {
            $calls++;
        });
        $dispatcher->listen(TestEvent::class, function () use (&$calls): void {
            $calls++;
        });

        $dispatcher->dispatch(new TestEvent('test'));

        expect($calls)->toBe(2);
    });

    it('ignores events with no listeners', function (): void {
        $dispatcher = new EventDispatcher;

        $dispatcher->dispatch(new TestEvent('ignored'));

        expect(true)->toBeTrue();
    });

    it('returns itself from listen for chaining', function (): void {
        $dispatcher = new EventDispatcher;

        $result = $dispatcher
            ->listen(TestEvent::class, fn () => null)
            ->listen(TestEvent::class, fn () => null);

        expect($result)->toBeInstanceOf(EventDispatcher::class);
    });

});

class TestEvent
{
    public function __construct(public readonly string $message) {}
}
