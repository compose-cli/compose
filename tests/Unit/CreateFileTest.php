<?php

use Compose\Actions\File\CreateFile;
use Compose\Enums\FileOperation;

describe('CreateFile', function (): void {

    // -------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------

    describe('execution', function (): void {

        it('creates a file with contents', function (): void {
            $action = (new CreateFile(
                path: 'hello.txt',
                contents: 'Hello, world!',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute($action->context ?? context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('hello.txt')))->toBeTrue();
            expect(file_get_contents($this->tempPath('hello.txt')))->toBe('Hello, world!');
        });

        it('creates an empty file', function (): void {
            $action = (new CreateFile(
                path: 'empty.txt',
                contents: '',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('empty.txt')))->toBeTrue();
            expect(file_get_contents($this->tempPath('empty.txt')))->toBe('');
        });

        it('creates parent directories automatically', function (): void {
            $action = (new CreateFile(
                path: 'deep/nested/dir/file.txt',
                contents: 'nested',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('deep/nested/dir/file.txt')))->toBeTrue();
            expect(file_get_contents($this->tempPath('deep/nested/dir/file.txt')))->toBe('nested');
        });

        it('overwrites existing files in overwrite mode', function (): void {
            $this->createFile('existing.txt', 'old content');

            $action = (new CreateFile(
                path: 'existing.txt',
                contents: 'new content',
                overwrite: true,
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('existing.txt')))->toBe('new content');
        });

        it('skips existing files when overwrite is false', function (): void {
            $this->createFile('existing.txt', 'original');

            $action = (new CreateFile(
                path: 'existing.txt',
                contents: 'replacement',
                overwrite: false,
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toContain('Skipped');
            expect(file_get_contents($this->tempPath('existing.txt')))->toBe('original');
        });

        it('handles multi-line file contents', function (): void {
            $contents = <<<'PHP'
                <?php

                namespace App\Models;

                class Team extends Model
                {
                    protected $fillable = ['name', 'slug'];
                }
                PHP;

            $action = (new CreateFile(
                path: 'app/Models/Team.php',
                contents: $contents,
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('app/Models/Team.php')))->toBe($contents);
        });

        it('reports byte count in output', function (): void {
            $action = (new CreateFile(
                path: 'file.txt',
                contents: 'twelve chars',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->output)->toContain('12 bytes');
        });

    });

    // -------------------------------------------------------------------
    // Describe
    // -------------------------------------------------------------------

    describe('describe', function (): void {

        it('describes with byte count', function (): void {
            $action = new CreateFile(path: 'config/app.php', contents: 'hello');

            expect($action->describe())->toBe('create config/app.php (5 bytes)');
        });

        it('describes empty files', function (): void {
            $action = new CreateFile(path: 'empty.txt', contents: '');

            expect($action->describe())->toBe('create empty.txt (empty)');
        });

    });

    // -------------------------------------------------------------------
    // Rollback
    // -------------------------------------------------------------------

    describe('rollback', function (): void {

        it('can be rolled back', function (): void {
            $action = new CreateFile(path: 'file.txt', contents: 'hello');

            expect($action->canBeRolledBack())->toBeTrue();
            expect($action->canRollbackDirect())->toBeTrue();
        });

        it('deletes the file on rollback', function (): void {
            $this->createFile('to-rollback.txt', 'will be deleted');

            $action = (new CreateFile(
                path: 'to-rollback.txt',
                contents: 'will be deleted',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('to-rollback.txt')))->toBeFalse();
        });

        it('succeeds even if file is already gone', function (): void {
            $action = (new CreateFile(
                path: 'nonexistent.txt',
                contents: '',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
        });

    });

    // -------------------------------------------------------------------
    // Operation Type
    // -------------------------------------------------------------------

    it('reports the correct operation type', function (): void {
        $action = new CreateFile(path: 'file.txt', contents: '');

        expect($action)->toBeOperation(FileOperation::Create);
    });

    // -------------------------------------------------------------------
    // Direct Execution Flag
    // -------------------------------------------------------------------

    it('is a direct action (no shell command)', function (): void {
        $action = new CreateFile(path: 'file.txt', contents: '');

        expect($action->isDirect())->toBeTrue();
        expect($action->command())->toBeNull();
    });

    // -------------------------------------------------------------------
    // Overwrite Flag
    // -------------------------------------------------------------------

    it('defaults to overwrite mode', function (): void {
        $action = new CreateFile(path: 'file.txt', contents: '');

        expect($action->overwrite)->toBeTrue();
    });

});
