<?php

declare(strict_types=1);

use Compose\Builders\InstructBuilder;

describe('InstructBuilder', function (): void {
    // -------------------------------------------------------------------
    // Accumulation
    // -------------------------------------------------------------------

    describe('accumulation', function (): void {
        it('accumulates creating paths', function (): void {
            $builder = new InstructBuilder;
            $payload = $builder->creating('a.php', 'b.php')->toPayload('test');

            expect($payload->creating)->toBe(['a.php', 'b.php']);
        });

        it('accumulates modifying paths', function (): void {
            $builder = new InstructBuilder;
            $payload = $builder->modifying('a.php', 'b.php')->toPayload('test');

            expect($payload->modifying)->toBe(['a.php', 'b.php']);
        });

        it('accumulates using entries with default include false', function (): void {
            $builder = new InstructBuilder;
            $payload = $builder->using('a.php')->using('b.php')->toPayload('test');

            expect($payload->using)->toBe([
                ['path' => 'a.php', 'include' => false],
                ['path' => 'b.php', 'include' => false],
            ]);
        });

        it('respects include flag on using', function (): void {
            $builder = new InstructBuilder;
            $payload = $builder->using('a.php', include: true)->toPayload('test');

            expect($payload->using)->toBe([
                ['path' => 'a.php', 'include' => true],
            ]);
        });

        it('accumulates like paths', function (): void {
            $builder = new InstructBuilder;
            $payload = $builder->like('a.php', 'b.php')->toPayload('test');

            expect($payload->like)->toBe(['a.php', 'b.php']);
        });

        it('accumulates testing paths', function (): void {
            $builder = new InstructBuilder;
            $payload = $builder->testing('test_a.php', 'test_b.php')->toPayload('test');

            expect($payload->testing)->toBe(['test_a.php', 'test_b.php']);
        });
    });

    // -------------------------------------------------------------------
    // Merge behavior
    // -------------------------------------------------------------------

    describe('merge behavior', function (): void {
        it('merges rules across multiple calls', function (): void {
            $builder = new InstructBuilder;
            $payload = $builder
                ->rules(['rule 1'])
                ->rules(['rule 2', 'rule 3'])
                ->toPayload('test');

            expect($payload->rules)->toBe(['rule 1', 'rule 2', 'rule 3']);
        });

        it('merges context across multiple calls', function (): void {
            $builder = new InstructBuilder;
            $payload = $builder
                ->with(['framework' => 'Livewire'])
                ->with(['php' => '8.3'])
                ->toPayload('test');

            expect($payload->context)->toBe(['framework' => 'Livewire', 'php' => '8.3']);
        });
    });

    // -------------------------------------------------------------------
    // toPayload
    // -------------------------------------------------------------------

    describe('toPayload', function (): void {
        it('produces a complete payload with all fields', function (): void {
            $builder = new InstructBuilder;
            $payload = $builder
                ->creating('new.php')
                ->modifying('existing.php')
                ->using('context.php')
                ->like('style.php')
                ->rules(['be concise'])
                ->with(['php' => '8.3'])
                ->testing('test.php')
                ->toPayload('Create a widget');

            expect($payload->description)->toBe('Create a widget');
            expect($payload->creating)->toBe(['new.php']);
            expect($payload->modifying)->toBe(['existing.php']);
            expect($payload->using)->toHaveCount(1);
            expect($payload->like)->toBe(['style.php']);
            expect($payload->rules)->toBe(['be concise']);
            expect($payload->context)->toBe(['php' => '8.3']);
            expect($payload->testing)->toBe(['test.php']);
            expect($payload->bake)->toBeFalse();
        });

        it('produces an empty payload when nothing is configured', function (): void {
            $payload = (new InstructBuilder)->toPayload('bare');

            expect($payload->description)->toBe('bare');
            expect($payload->creating)->toBe([]);
            expect($payload->modifying)->toBe([]);
            expect($payload->using)->toBe([]);
            expect($payload->like)->toBe([]);
            expect($payload->rules)->toBe([]);
            expect($payload->context)->toBe([]);
            expect($payload->testing)->toBe([]);
        });
    });

    // -------------------------------------------------------------------
    // bake
    // -------------------------------------------------------------------

    it('accepts bake flag', function (): void {
        $payload = (new InstructBuilder)->bake()->toPayload('test');

        expect($payload->bake)->toBeTrue();
    });

    it('defaults bake to false', function (): void {
        $payload = (new InstructBuilder)->toPayload('test');

        expect($payload->bake)->toBeFalse();
    });
});
