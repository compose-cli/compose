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
                ->toThrow(InvalidArgumentException::class, 'File path is required');
        });

        it('throws on github shorthand without colon separator', function (): void {
            expect(fn () => (new Sink(from: 'github:laravel/laravel'))->resolveUrl())
                ->toThrow(InvalidArgumentException::class, 'Expected format');
        });

        it('throws on github shorthand without owner/repo format', function (): void {
            expect(fn () => (new Sink(from: 'github:laravel:file.php'))->resolveUrl())
                ->toThrow(InvalidArgumentException::class, 'owner/repo format');
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
    // Execution
    // -------------------------------------------------------------------

    describe('execution', function (): void {

        it('is a direct-execution action', function (): void {
            $action = new Sink(
                from: 'https://example.com/file.yml',
                to: 'file.yml',
            );

            expect($action->isDirect())->toBeTrue();
        });

        it('skips download when force is false and target exists', function (): void {
            $target = 'existing.yml';
            $this->createFile($target, 'original content');

            $action = (new Sink(
                from: 'https://example.com/file.yml',
                to: $target,
                force: false,
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue()
                ->and($result->output)->toContain('Skipped (exists)')
                ->and(file_get_contents($this->tempPath.DIRECTORY_SEPARATOR.$target))->toBe('original content');
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

        it('can be rolled back when force is true', function (): void {
            $action = new Sink(
                from: 'https://example.com/file.yml',
                to: 'config/file.yml',
                force: true,
            );

            expect($action->canRollbackDirect())->toBeTrue();
        });

        it('cannot be rolled back when force is false', function (): void {
            $action = new Sink(
                from: 'https://example.com/file.yml',
                to: 'config/file.yml',
                force: false,
            );

            expect($action->canRollbackDirect())->toBeFalse();
        });

        it('rolls back by deleting the target file', function (): void {
            $target = 'rollback-test.yml';
            $this->createFile($target, 'fetched content');

            $action = (new Sink(
                from: 'https://example.com/file.yml',
                to: $target,
                force: true,
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue()
                ->and(file_exists($this->tempPath.DIRECTORY_SEPARATOR.$target))->toBeFalse();
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
