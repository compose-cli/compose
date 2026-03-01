<?php

use Compose\Actions\Sink;
use Compose\Enums\FileOperation;

describe('Sink', function (): void {

    // -------------------------------------------------------------------
    // URL Resolution
    // -------------------------------------------------------------------

    describe('URL resolution', function (): void {

        it('passes raw URLs through unchanged', function (): void {
            $action = new Sink(
                from: 'https://example.com/path/to/file.yml',
                to: 'file.yml',
            );

            expect($action->resolveUrl())->toBe('https://example.com/path/to/file.yml');
        });

        it('resolves github shorthand with ref', function (): void {
            $action = new Sink(
                from: 'github:laravel/laravel@11.x:.github/workflows/tests.yml',
            );

            expect($action->resolveUrl())
                ->toBe('https://raw.githubusercontent.com/laravel/laravel/11.x/.github/workflows/tests.yml');
        });

        it('resolves github shorthand without ref (defaults to main)', function (): void {
            $action = new Sink(
                from: 'github:laravel/laravel:phpunit.xml.dist',
            );

            expect($action->resolveUrl())
                ->toBe('https://raw.githubusercontent.com/laravel/laravel/main/phpunit.xml.dist');
        });

        it('resolves github shorthand with nested paths', function (): void {
            $action = new Sink(
                from: 'github:tallstackui/tallstackui@2.x:stubs/views/form/input.blade.php',
            );

            expect($action->resolveUrl())
                ->toBe('https://raw.githubusercontent.com/tallstackui/tallstackui/2.x/stubs/views/form/input.blade.php');
        });

        it('resolves github shorthand with tag refs', function (): void {
            $action = new Sink(
                from: 'github:spatie/laravel-permission@6.10.1:config/permission.php',
            );

            expect($action->resolveUrl())
                ->toBe('https://raw.githubusercontent.com/spatie/laravel-permission/6.10.1/config/permission.php');
        });

        it('throws on github shorthand without path', function (): void {
            expect(fn () => (new Sink(from: 'github:laravel/laravel@11.x:'))->resolveUrl())
                ->toThrow(\InvalidArgumentException::class, 'File path is required');
        });

        it('throws on github shorthand without colon separator', function (): void {
            expect(fn () => (new Sink(from: 'github:laravel/laravel'))->resolveUrl())
                ->toThrow(\InvalidArgumentException::class, 'Expected format');
        });

        it('throws on github shorthand without owner/repo format', function (): void {
            expect(fn () => (new Sink(from: 'github:laravel:file.php'))->resolveUrl())
                ->toThrow(\InvalidArgumentException::class, 'owner/repo format');
        });

        it('caches the resolved URL', function (): void {
            $action = new Sink(from: 'github:laravel/laravel@11.x:phpunit.xml.dist');

            $first = $action->resolveUrl();
            $second = $action->resolveUrl();

            expect($first)->toBe($second);
        });

    });

    // -------------------------------------------------------------------
    // Target Resolution
    // -------------------------------------------------------------------

    describe('target resolution', function (): void {

        it('uses explicit target when provided', function (): void {
            $action = new Sink(
                from: 'https://example.com/some/deep/path/file.yml',
                to: 'custom/location.yml',
            );

            expect($action->resolveTarget())->toBe('custom/location.yml');
        });

        it('derives target from github shorthand path', function (): void {
            $action = new Sink(
                from: 'github:laravel/laravel@11.x:.github/workflows/tests.yml',
            );

            expect($action->resolveTarget())->toBe('.github/workflows/tests.yml');
        });

        it('derives target from raw URL path', function (): void {
            $action = new Sink(
                from: 'https://raw.githubusercontent.com/laravel/laravel/11.x/phpunit.xml.dist',
            );

            expect($action->resolveTarget())->toBe('raw.githubusercontent.com/laravel/laravel/11.x/phpunit.xml.dist');
        });

        it('explicit target overrides github shorthand derivation', function (): void {
            $action = new Sink(
                from: 'github:laravel/laravel@11.x:phpunit.xml.dist',
                to: 'phpunit.xml',
            );

            expect($action->resolveTarget())->toBe('phpunit.xml');
        });

    });

    // -------------------------------------------------------------------
    // Command Generation
    // -------------------------------------------------------------------

    describe('command generation', function (): void {

        it('generates a curl command for raw URLs', function (): void {
            $action = (new Sink(
                from: 'https://example.com/file.yml',
                to: 'file.yml',
            ))->withContext(context());

            expect($action)->toGenerateCommand('curl -fsSL -o file.yml https://example.com/file.yml');
        });

        it('generates a curl command with resolved github URL', function (): void {
            $action = (new Sink(
                from: 'github:laravel/laravel@11.x:phpunit.xml.dist',
                to: 'phpunit.xml.dist',
            ))->withContext(context());

            expect($action)->toGenerateCommand(
                'curl -fsSL -o phpunit.xml.dist https://raw.githubusercontent.com/laravel/laravel/11.x/phpunit.xml.dist',
            );
        });

        it('generates a curl command with derived target', function (): void {
            $action = (new Sink(
                from: 'github:laravel/laravel@11.x:.github/workflows/tests.yml',
            ))->withContext(context());

            expect($action)->toGenerateCommand(
                'curl -fsSL -o .github/workflows/tests.yml https://raw.githubusercontent.com/laravel/laravel/11.x/.github/workflows/tests.yml',
            );
        });

        it('returns the correct command array', function (): void {
            $action = (new Sink(
                from: 'https://example.com/file.yml',
                to: 'output.yml',
            ))->withContext(context());

            expect($action->command()->toArray())
                ->toBe(['curl', '-fsSL', '-o', 'output.yml', 'https://example.com/file.yml']);
        });

    });

    // -------------------------------------------------------------------
    // Operation Type
    // -------------------------------------------------------------------

    it('reports the correct operation type', function (): void {
        $action = new Sink(from: 'https://example.com/file.yml', to: 'file.yml');

        expect($action)->toBeOperation(FileOperation::Sink);
    });

    // -------------------------------------------------------------------
    // Rollback
    // -------------------------------------------------------------------

    describe('rollback', function (): void {

        it('can be rolled back', function (): void {
            $action = (new Sink(
                from: 'https://example.com/file.yml',
                to: 'config/file.yml',
            ))->withContext(context());

            expect($action->canBeRolledBack())->toBeTrue();
        });

        it('rolls back by deleting the target file', function (): void {
            $action = (new Sink(
                from: 'https://example.com/file.yml',
                to: 'config/file.yml',
            ))->withContext(context());

            $expected = PHP_OS_FAMILY === 'Windows'
                ? 'cmd /c del /q config/file.yml'
                : 'rm -f config/file.yml';

            expect($action->rollback()->toString())->toBe($expected);
        });

        it('rolls back with derived target', function (): void {
            $action = (new Sink(
                from: 'github:laravel/laravel@11.x:phpunit.xml.dist',
            ))->withContext(context());

            $expected = PHP_OS_FAMILY === 'Windows'
                ? 'cmd /c del /q phpunit.xml.dist'
                : 'rm -f phpunit.xml.dist';

            expect($action->rollback()->toString())->toBe($expected);
        });

    });

    // -------------------------------------------------------------------
    // Describe
    // -------------------------------------------------------------------

    describe('describe', function (): void {

        it('describes with github shorthand', function (): void {
            $action = new Sink(
                from: 'github:laravel/laravel@11.x:phpunit.xml.dist',
                to: 'phpunit.xml.dist',
            );

            expect($action->describe())->toBe('sink github:laravel/laravel@11.x:phpunit.xml.dist → phpunit.xml.dist');
        });

        it('describes with short raw URL', function (): void {
            $action = new Sink(
                from: 'https://example.com/file.yml',
                to: 'file.yml',
            );

            expect($action->describe())->toBe('sink https://example.com/file.yml → file.yml');
        });

        it('shortens long URLs', function (): void {
            $action = new Sink(
                from: 'https://raw.githubusercontent.com/some-very-long-org/some-very-long-repo/main/some/deeply/nested/path/to/file.yml',
                to: 'file.yml',
            );

            expect($action->describe())->toBe('sink raw.githubusercontent.com/.../file.yml → file.yml');
        });

    });

    // -------------------------------------------------------------------
    // Force flag
    // -------------------------------------------------------------------

    it('defaults to force mode', function (): void {
        $action = new Sink(from: 'https://example.com/file.yml', to: 'file.yml');

        expect($action->force)->toBeTrue();
    });

    it('can be set to non-force mode', function (): void {
        $action = new Sink(from: 'https://example.com/file.yml', to: 'file.yml', force: false);

        expect($action->force)->toBeFalse();
    });

});
