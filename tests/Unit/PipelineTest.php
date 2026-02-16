<?php

use Compose\Execution\Pipeline;

describe('Pipeline', function (): void {

    it('sends a value through pipes in order', function (): void {
        $result = (new Pipeline)
            ->send(10)
            ->through([
                new class
                {
                    public function handle(int $value, Closure $next): mixed
                    {
                        return $next($value + 5);
                    }
                },
                new class
                {
                    public function handle(int $value, Closure $next): mixed
                    {
                        return $next($value * 2);
                    }
                },
            ])
            ->thenReturn();

        expect($result)->toBe(30); // (10 + 5) * 2
    });

    it('returns the passable when no pipes', function (): void {
        $result = (new Pipeline)
            ->send('hello')
            ->through([])
            ->thenReturn();

        expect($result)->toBe('hello');
    });

    it('a pipe can short-circuit', function (): void {
        $result = (new Pipeline)
            ->send(10)
            ->through([
                new class
                {
                    public function handle(int $value, Closure $next): mixed
                    {
                        return 'stopped';
                    }
                },
                new class
                {
                    public function handle(int $value, Closure $next): mixed
                    {
                        return $next($value * 100);
                    }
                },
            ])
            ->thenReturn();

        expect($result)->toBe('stopped');
    });

});
