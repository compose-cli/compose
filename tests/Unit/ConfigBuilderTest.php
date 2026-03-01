<?php

use Compose\Builders\ConfigBuilder;

describe('ConfigBuilder', function (): void {

    // -------------------------------------------------------------------
    // Basic Operations
    // -------------------------------------------------------------------

    describe('operations', function (): void {

        it('queues a set operation', function (): void {
            $builder = new ConfigBuilder;
            $builder->set('teams', true);

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('set');
            expect($ops[0]['key'])->toBe('teams');
            expect($ops[0]['value'])->toBeTrue();
        });

        it('queues a remove operation', function (): void {
            $builder = new ConfigBuilder;
            $builder->remove('cache.store');

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('remove');
            expect($ops[0]['key'])->toBe('cache.store');
        });

        it('queues a merge operation', function (): void {
            $builder = new ConfigBuilder;
            $builder->merge('guard_names', ['web', 'api']);

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('merge');
            expect($ops[0]['key'])->toBe('guard_names');
            expect($ops[0]['value'])->toBe(['web', 'api']);
        });

        it('queues a push operation', function (): void {
            $builder = new ConfigBuilder;
            $builder->push('providers', 'AuthServiceProvider');

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('push');
            expect($ops[0]['key'])->toBe('providers');
            expect($ops[0]['value'])->toBe('AuthServiceProvider');
        });

        it('queues a comment operation', function (): void {
            $builder = new ConfigBuilder;
            $builder->comment('display_permission_in_exception');

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('comment');
            expect($ops[0]['key'])->toBe('display_permission_in_exception');
        });

    });

    // -------------------------------------------------------------------
    // Chaining
    // -------------------------------------------------------------------

    it('supports fluent chaining', function (): void {
        $builder = new ConfigBuilder;

        $result = $builder
            ->set('teams', true)
            ->merge('guard_names', ['web', 'api'])
            ->remove('cache')
            ->push('providers', 'MyProvider')
            ->comment('debug');

        expect($result)->toBe($builder);
        expect($builder->operations())->toHaveCount(5);
    });

    it('preserves operation order', function (): void {
        $builder = new ConfigBuilder;

        $builder
            ->set('first', 1)
            ->remove('second')
            ->merge('third', ['a'])
            ->push('fourth', 'b');

        $ops = $builder->operations();

        expect($ops[0]['type'])->toBe('set');
        expect($ops[1]['type'])->toBe('remove');
        expect($ops[2]['type'])->toBe('merge');
        expect($ops[3]['type'])->toBe('push');
    });

});
