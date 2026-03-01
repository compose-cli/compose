<?php

use Compose\Builders\EnvBuilder;
use Compose\Support\TextFile\EnvFileParser;

describe('EnvBuilder', function (): void {

    // -------------------------------------------------------------------
    // Basic Operations
    // -------------------------------------------------------------------

    describe('operations', function (): void {

        it('queues a set operation', function (): void {
            $builder = new EnvBuilder;
            $builder->set('APP_NAME', 'My App');

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('set');
            expect($ops[0]['key'])->toBe('APP_NAME');
            expect($ops[0]['value'])->toBe('My App');
            expect($ops[0]['after'])->toBeNull();
        });

        it('queues a remove operation', function (): void {
            $builder = new EnvBuilder;
            $builder->remove('APP_DEBUG');

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('remove');
            expect($ops[0]['key'])->toBe('APP_DEBUG');
        });

        it('queues a comment operation', function (): void {
            $builder = new EnvBuilder;
            $builder->comment('REDIS_HOST');

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('comment');
            expect($ops[0]['key'])->toBe('REDIS_HOST');
        });

        it('queues an uncomment operation', function (): void {
            $builder = new EnvBuilder;
            $builder->uncomment('REDIS_HOST');

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('uncomment');
            expect($ops[0]['key'])->toBe('REDIS_HOST');
        });

        it('queues multiple set operations from merge', function (): void {
            $builder = new EnvBuilder;
            $builder->merge(['APP_NAME' => 'My App', 'APP_ENV' => 'local']);

            $ops = $builder->operations();

            expect($ops)->toHaveCount(2);
            expect($ops[0]['type'])->toBe('set');
            expect($ops[0]['key'])->toBe('APP_NAME');
            expect($ops[1]['type'])->toBe('set');
            expect($ops[1]['key'])->toBe('APP_ENV');
        });

        it('queues a section operation', function (): void {
            $builder = new EnvBuilder;
            $builder->section('# Permissions', ['TEAMS' => 'true']);

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('section');
            expect($ops[0]['header'])->toBe('# Permissions');
            expect($ops[0]['values'])->toBe(['TEAMS' => 'true']);
        });

    });

    // -------------------------------------------------------------------
    // After Positioning
    // -------------------------------------------------------------------

    describe('after', function (): void {

        it('sets the after key on the next set operation', function (): void {
            $builder = new EnvBuilder;
            $builder->after('APP_NAME')->set('APP_ENV', 'local');

            $ops = $builder->operations();

            expect($ops[0]['after'])->toBe('APP_NAME');
        });

        it('resets after key after use', function (): void {
            $builder = new EnvBuilder;
            $builder->after('APP_NAME')->set('APP_ENV', 'local')->set('APP_DEBUG', 'true');

            $ops = $builder->operations();

            expect($ops[0]['after'])->toBe('APP_NAME');
            expect($ops[1]['after'])->toBeNull();
        });

        it('sets the after key on a section operation', function (): void {
            $builder = new EnvBuilder;
            $builder->after('DB_PASSWORD')->section('# Cache', ['CACHE_DRIVER' => 'redis']);

            $ops = $builder->operations();

            expect($ops[0]['after'])->toBe('DB_PASSWORD');
        });

    });

    // -------------------------------------------------------------------
    // When (Conditional)
    // -------------------------------------------------------------------

    describe('when', function (): void {

        it('queues a when operation with key-equals-value form', function (): void {
            $callback = fn (EnvBuilder $e) => $e->set('TELESCOPE_ENABLED', 'true');
            $builder = new EnvBuilder;
            $builder->when('APP_DEBUG', 'true', $callback);

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('when');
            expect($ops[0]['condition'])->toBeInstanceOf(Closure::class);
            expect($ops[0]['callback'])->toBe($callback);
        });

        it('key-equals-value condition resolves correctly', function (): void {
            $builder = new EnvBuilder;
            $builder->when('APP_DEBUG', 'true', fn (EnvBuilder $e) => $e->set('X', 'Y'));

            $ops = $builder->operations();
            $condition = $ops[0]['condition'];

            $parserMatch = EnvFileParser::parse('APP_DEBUG=true');
            $parserNoMatch = EnvFileParser::parse('APP_DEBUG=false');

            expect($condition($parserMatch))->toBeTrue();
            expect($condition($parserNoMatch))->toBeFalse();
        });

        it('queues a when operation with key-exists form', function (): void {
            $callback = fn (EnvBuilder $e) => $e->set('TELESCOPE_ENABLED', 'true');
            $builder = new EnvBuilder;
            $builder->when('APP_DEBUG', $callback);

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('when');
            expect($ops[0]['callback'])->toBe($callback);
        });

        it('key-exists condition resolves correctly', function (): void {
            $builder = new EnvBuilder;
            $builder->when('APP_DEBUG', fn (EnvBuilder $e) => $e->set('X', 'Y'));

            $ops = $builder->operations();
            $condition = $ops[0]['condition'];

            $parserHas = EnvFileParser::parse('APP_DEBUG=false');
            $parserMissing = EnvFileParser::parse('APP_NAME=Laravel');

            expect($condition($parserHas))->toBeTrue();
            expect($condition($parserMissing))->toBeFalse();
        });

        it('queues a when operation with custom closure form', function (): void {
            $condition = fn (EnvFileParser $p) => $p->get('APP_ENV') === 'production';
            $callback = fn (EnvBuilder $e) => $e->set('LOG_LEVEL', 'error');

            $builder = new EnvBuilder;
            $builder->when($condition, $callback);

            $ops = $builder->operations();

            expect($ops)->toHaveCount(1);
            expect($ops[0]['type'])->toBe('when');
            expect($ops[0]['condition'])->toBe($condition);
            expect($ops[0]['callback'])->toBe($callback);
        });

    });

    // -------------------------------------------------------------------
    // Chaining
    // -------------------------------------------------------------------

    it('supports fluent chaining', function (): void {
        $builder = new EnvBuilder;

        $result = $builder
            ->set('APP_NAME', 'My App')
            ->remove('APP_EXAMPLE')
            ->comment('REDIS_HOST')
            ->section('# Permissions', ['TEAMS' => 'true']);

        expect($result)->toBe($builder);
        expect($builder->operations())->toHaveCount(4);
    });

});
