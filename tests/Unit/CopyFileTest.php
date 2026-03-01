<?php

use Compose\Actions\File\CopyFile;
use Compose\Enums\FileOperation;

describe('CopyFile', function (): void {

    // -------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------

    describe('execution', function (): void {

        it('copies a file to a new location', function (): void {
            $this->createFile('source.txt', 'hello world');

            $action = (new CopyFile(
                from: 'source.txt',
                to: 'dest.txt',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('dest.txt')))->toBeTrue();
            expect(file_get_contents($this->tempPath('dest.txt')))->toBe('hello world');
            expect(file_exists($this->tempPath('source.txt')))->toBeTrue();
        });

        it('creates parent directories for the target', function (): void {
            $this->createFile('source.txt', 'nested copy');

            $action = (new CopyFile(
                from: 'source.txt',
                to: 'deep/nested/dir/dest.txt',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('deep/nested/dir/dest.txt')))->toBeTrue();
            expect(file_get_contents($this->tempPath('deep/nested/dir/dest.txt')))->toBe('nested copy');
        });

        it('overwrites existing target in overwrite mode', function (): void {
            $this->createFile('source.txt', 'new content');
            $this->createFile('dest.txt', 'old content');

            $action = (new CopyFile(
                from: 'source.txt',
                to: 'dest.txt',
                overwrite: true,
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('dest.txt')))->toBe('new content');
        });

        it('skips existing target when overwrite is false', function (): void {
            $this->createFile('source.txt', 'new content');
            $this->createFile('dest.txt', 'original');

            $action = (new CopyFile(
                from: 'source.txt',
                to: 'dest.txt',
                overwrite: false,
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toContain('Skipped');
            expect(file_get_contents($this->tempPath('dest.txt')))->toBe('original');
        });

        it('fails when source file does not exist', function (): void {
            $action = (new CopyFile(
                from: 'missing.txt',
                to: 'dest.txt',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeFalse();
            expect($result->errorOutput)->toContain('not found');
        });

        it('copies files from nested directories', function (): void {
            $this->createFile('config/app.php', '<?php return [];');

            $action = (new CopyFile(
                from: 'config/app.php',
                to: 'backup/config/app.php',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('backup/config/app.php')))->toBe('<?php return [];');
        });

        it('includes arrow in output', function (): void {
            $this->createFile('a.txt', 'data');

            $action = (new CopyFile(
                from: 'a.txt',
                to: 'b.txt',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->output)->toContain('→');
        });

    });

    // -------------------------------------------------------------------
    // Describe
    // -------------------------------------------------------------------

    describe('describe', function (): void {

        it('describes with source and target', function (): void {
            $action = new CopyFile(from: 'src/file.php', to: 'dest/file.php');

            expect($action->describe())->toBe('copy src/file.php → dest/file.php');
        });

    });

    // -------------------------------------------------------------------
    // Rollback
    // -------------------------------------------------------------------

    describe('rollback', function (): void {

        it('can be rolled back', function (): void {
            $action = new CopyFile(from: 'a.txt', to: 'b.txt');

            expect($action->canBeRolledBack())->toBeTrue();
            expect($action->canRollbackDirect())->toBeTrue();
        });

        it('deletes the target on rollback when file was newly created', function (): void {
            $this->createFile('source.txt', 'data');

            $action = (new CopyFile(
                from: 'source.txt',
                to: 'new-target.txt',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $action->execute(context(workingDirectory: $this->tempPath));

            expect(file_exists($this->tempPath('new-target.txt')))->toBeTrue();

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('new-target.txt')))->toBeFalse();
        });

        it('restores original contents on rollback when target was overwritten', function (): void {
            $this->createFile('source.txt', 'new content');
            $this->createFile('existing.txt', 'original content');

            $action = (new CopyFile(
                from: 'source.txt',
                to: 'existing.txt',
                overwrite: true,
            ))->withContext(context(workingDirectory: $this->tempPath));

            $action->execute(context(workingDirectory: $this->tempPath));

            expect(file_get_contents($this->tempPath('existing.txt')))->toBe('new content');

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toContain('Restored');
            expect(file_get_contents($this->tempPath('existing.txt')))->toBe('original content');
        });

        it('succeeds even if target is already gone', function (): void {
            $action = (new CopyFile(
                from: 'source.txt',
                to: 'nonexistent.txt',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
        });

    });

    // -------------------------------------------------------------------
    // Operation Type
    // -------------------------------------------------------------------

    it('reports the correct operation type', function (): void {
        $action = new CopyFile(from: 'a.txt', to: 'b.txt');

        expect($action)->toBeOperation(FileOperation::Copy);
    });

    // -------------------------------------------------------------------
    // Direct Execution Flag
    // -------------------------------------------------------------------

    it('is a direct action (no shell command)', function (): void {
        $action = new CopyFile(from: 'a.txt', to: 'b.txt');

        expect($action->isDirect())->toBeTrue();
        expect($action->command())->toBeNull();
    });

    // -------------------------------------------------------------------
    // Overwrite Flag
    // -------------------------------------------------------------------

    it('defaults to overwrite mode', function (): void {
        $action = new CopyFile(from: 'a.txt', to: 'b.txt');

        expect($action->overwrite)->toBeTrue();
    });

});
