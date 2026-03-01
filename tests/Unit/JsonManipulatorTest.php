<?php

use Compose\Support\JsonFile\JsonManipulator;

describe('JsonManipulator', function (): void {

    describe('set', function (): void {

        it('sets a top-level key', function (): void {
            $m = new JsonManipulator('{"name": "old"}');
            $m->set('name', 'new');

            $result = json_decode($m->toString(), true);
            expect($result['name'])->toBe('new');
        });

        it('sets a nested key with dot notation', function (): void {
            $m = new JsonManipulator('{"scripts": {}}');
            $m->set('scripts.build', 'vite build');

            $result = json_decode($m->toString(), true);
            expect($result['scripts']['build'])->toBe('vite build');
        });

        it('creates intermediate keys', function (): void {
            $m = new JsonManipulator('{}');
            $m->set('deeply.nested.key', 'value');

            $result = json_decode($m->toString(), true);
            expect($result['deeply']['nested']['key'])->toBe('value');
        });

    });

    describe('merge', function (): void {

        it('merges into an existing array', function (): void {
            $m = new JsonManipulator('{"dependencies": {"vue": "^3.0"}}');
            $m->merge('dependencies', ['axios' => '^1.0']);

            $result = json_decode($m->toString(), true);
            expect($result['dependencies'])->toBe(['vue' => '^3.0', 'axios' => '^1.0']);
        });

        it('creates the key if it does not exist', function (): void {
            $m = new JsonManipulator('{}');
            $m->merge('dependencies', ['vue' => '^3.0']);

            $result = json_decode($m->toString(), true);
            expect($result['dependencies'])->toBe(['vue' => '^3.0']);
        });

    });

    describe('remove', function (): void {

        it('removes a top-level key', function (): void {
            $m = new JsonManipulator('{"name": "app", "version": "1.0"}');
            $m->remove('version');

            $result = json_decode($m->toString(), true);
            expect($result)->not->toHaveKey('version');
            expect($result)->toHaveKey('name');
        });

        it('removes a nested key', function (): void {
            $m = new JsonManipulator('{"scripts": {"build": "vite", "dev": "vite dev"}}');
            $m->remove('scripts.dev');

            $result = json_decode($m->toString(), true);
            expect($result['scripts'])->not->toHaveKey('dev');
            expect($result['scripts'])->toHaveKey('build');
        });

        it('does nothing if key does not exist', function (): void {
            $m = new JsonManipulator('{"name": "app"}');
            $m->remove('nonexistent');

            $result = json_decode($m->toString(), true);
            expect($result)->toBe(['name' => 'app']);
        });

    });

    describe('push', function (): void {

        it('pushes a value onto an array', function (): void {
            $m = new JsonManipulator('{"tags": ["php"]}');
            $m->push('tags', 'laravel');

            $result = json_decode($m->toString(), true);
            expect($result['tags'])->toBe(['php', 'laravel']);
        });

        it('creates the array if it does not exist', function (): void {
            $m = new JsonManipulator('{}');
            $m->push('tags', 'php');

            $result = json_decode($m->toString(), true);
            expect($result['tags'])->toBe(['php']);
        });

    });

    describe('chaining', function (): void {

        it('supports fluent method chaining', function (): void {
            $m = new JsonManipulator('{}');

            $result = $m->set('name', 'app')
                ->set('version', '1.0')
                ->merge('scripts', ['build' => 'vite build'])
                ->push('keywords', 'php')
                ->toString();

            $data = json_decode($result, true);
            expect($data['name'])->toBe('app');
            expect($data['version'])->toBe('1.0');
            expect($data['scripts']['build'])->toBe('vite build');
            expect($data['keywords'])->toBe(['php']);
        });

    });

    describe('validation', function (): void {

        it('throws on invalid JSON input', function (): void {
            new JsonManipulator('not json');
        })->throws(RuntimeException::class, 'Invalid JSON');

    });

});
