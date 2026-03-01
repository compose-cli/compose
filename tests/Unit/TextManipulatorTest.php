<?php

use Compose\Support\TextFile\TextManipulator;

describe('TextManipulator', function (): void {

    describe('replace', function (): void {

        it('replaces a substring', function (): void {
            $result = TextManipulator::replace('Hello World', 'World', 'PHP');

            expect($result)->toBe('Hello PHP');
        });

        it('replaces all occurrences', function (): void {
            $result = TextManipulator::replace('foo bar foo', 'foo', 'baz');

            expect($result)->toBe('baz bar baz');
        });

        it('returns unchanged content when search not found', function (): void {
            $result = TextManipulator::replace('Hello', 'missing', 'replaced');

            expect($result)->toBe('Hello');
        });

    });

    describe('replaceRegex', function (): void {

        it('replaces with a regex pattern', function (): void {
            $result = TextManipulator::replaceRegex('foo123bar', '/\d+/', '456');

            expect($result)->toBe('foo456bar');
        });

        it('throws on invalid regex', function (): void {
            set_error_handler(static fn () => true);

            try {
                TextManipulator::replaceRegex('test', '/[invalid/', 'x');
            } finally {
                restore_error_handler();
            }
        })->throws(RuntimeException::class, 'Invalid regex');

    });

    describe('prepend', function (): void {

        it('prepends text', function (): void {
            $result = TextManipulator::prepend('world', 'hello ');

            expect($result)->toBe('hello world');
        });

    });

    describe('append', function (): void {

        it('appends text', function (): void {
            $result = TextManipulator::append('hello', ' world');

            expect($result)->toBe('hello world');
        });

    });

    describe('insertAfter', function (): void {

        it('inserts text after a marker', function (): void {
            $result = TextManipulator::insertAfter("line1\nline2\nline3", "line1\n", "inserted\n");

            expect($result)->toBe("line1\ninserted\nline2\nline3");
        });

        it('returns unchanged content when marker not found', function (): void {
            $result = TextManipulator::insertAfter('content', 'missing', 'inserted');

            expect($result)->toBe('content');
        });

    });

    describe('insertBefore', function (): void {

        it('inserts text before a marker', function (): void {
            $result = TextManipulator::insertBefore("line1\nline2\nline3", 'line2', "inserted\n");

            expect($result)->toBe("line1\ninserted\nline2\nline3");
        });

        it('returns unchanged content when marker not found', function (): void {
            $result = TextManipulator::insertBefore('content', 'missing', 'inserted');

            expect($result)->toBe('content');
        });

    });

});
