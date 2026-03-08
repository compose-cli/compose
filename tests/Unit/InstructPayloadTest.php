<?php

declare(strict_types=1);

use Compose\Payloads\InstructPayload;

describe('InstructPayload', function (): void {
    // -------------------------------------------------------------------
    // hash
    // -------------------------------------------------------------------

    describe('hash', function (): void {
        it('is deterministic for identical inputs', function (): void {
            $a = new InstructPayload('desc', ['a.php'], [], [], [], [], [], []);
            $b = new InstructPayload('desc', ['a.php'], [], [], [], [], [], []);

            expect($a->hash())->toBe($b->hash());
        });

        it('changes when description changes', function (): void {
            $a = new InstructPayload('desc one', [], [], [], [], [], [], []);
            $b = new InstructPayload('desc two', [], [], [], [], [], [], []);

            expect($a->hash())->not->toBe($b->hash());
        });

        it('changes when creating list changes', function (): void {
            $a = new InstructPayload('desc', ['a.php'], [], [], [], [], [], []);
            $b = new InstructPayload('desc', ['b.php'], [], [], [], [], [], []);

            expect($a->hash())->not->toBe($b->hash());
        });

        it('changes when rules change', function (): void {
            $a = new InstructPayload('desc', [], [], [], [], ['rule1'], [], []);
            $b = new InstructPayload('desc', [], [], [], [], ['rule2'], [], []);

            expect($a->hash())->not->toBe($b->hash());
        });

        it('changes when context changes', function (): void {
            $a = new InstructPayload('desc', [], [], [], [], [], ['php' => '8.3'], []);
            $b = new InstructPayload('desc', [], [], [], [], [], ['php' => '8.4'], []);

            expect($a->hash())->not->toBe($b->hash());
        });

        it('does not change when bake flag changes', function (): void {
            $a = new InstructPayload('desc', [], [], [], [], [], [], [], bake: false);
            $b = new InstructPayload('desc', [], [], [], [], [], [], [], bake: true);

            expect($a->hash())->toBe($b->hash());
        });
    });

    // -------------------------------------------------------------------
    // includedFiles / hintedFiles
    // -------------------------------------------------------------------

    describe('includedFiles', function (): void {
        it('returns only paths where include is true', function (): void {
            $payload = new InstructPayload(
                'desc', [], [], [
                    ['path' => 'a.php', 'include' => true],
                    ['path' => 'b.php', 'include' => false],
                    ['path' => 'c.php', 'include' => true],
                ], [], [], [], [],
            );

            expect($payload->includedFiles())->toBe(['a.php', 'c.php']);
        });

        it('returns empty when none are included', function (): void {
            $payload = new InstructPayload(
                'desc', [], [], [
                    ['path' => 'a.php', 'include' => false],
                ], [], [], [], [],
            );

            expect($payload->includedFiles())->toBe([]);
        });
    });

    describe('hintedFiles', function (): void {
        it('returns only paths where include is false', function (): void {
            $payload = new InstructPayload(
                'desc', [], [], [
                    ['path' => 'a.php', 'include' => true],
                    ['path' => 'b.php', 'include' => false],
                    ['path' => 'c.php', 'include' => false],
                ], [], [], [], [],
            );

            expect($payload->hintedFiles())->toBe(['b.php', 'c.php']);
        });

        it('returns empty when all are included', function (): void {
            $payload = new InstructPayload(
                'desc', [], [], [
                    ['path' => 'a.php', 'include' => true],
                ], [], [], [], [],
            );

            expect($payload->hintedFiles())->toBe([]);
        });
    });
});
