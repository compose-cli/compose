<?php

use Compose\Actions\File\DeleteFile;
use Compose\Enums\FileOperation;

describe('DeleteFile', function (): void {

    // -------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------

    describe('execution', function (): void {

        it('deletes a single file', function (): void {
            $this->createFile('delete-me.txt', 'goodbye');

            $action = (new DeleteFile('delete-me.txt'))
                ->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('delete-me.txt')))->toBeFalse();
            expect($result->output)->toContain('delete-me.txt');
        });

        it('deletes multiple files', function (): void {
            $this->createFile('one.txt', '');
            $this->createFile('two.txt', '');
            $this->createFile('three.txt', '');

            $action = (new DeleteFile('one.txt', 'two.txt', 'three.txt'))
                ->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('one.txt')))->toBeFalse();
            expect(file_exists($this->tempPath('two.txt')))->toBeFalse();
            expect(file_exists($this->tempPath('three.txt')))->toBeFalse();
        });

        it('deletes a directory recursively', function (): void {
            $this->createFile('mydir/sub/file.txt', 'nested');
            $this->createFile('mydir/other.txt', 'other');

            $action = (new DeleteFile('mydir'))
                ->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(is_dir($this->tempPath('mydir')))->toBeFalse();
        });

        it('deletes files matching a glob pattern', function (): void {
            $this->createFile('logs/app-2024-01.log', 'log1');
            $this->createFile('logs/app-2024-02.log', 'log2');
            $this->createFile('logs/app-2024-03.log', 'log3');
            $this->createFile('logs/keep.txt', 'keep');

            $action = (new DeleteFile('logs/*.log'))
                ->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('logs/app-2024-01.log')))->toBeFalse();
            expect(file_exists($this->tempPath('logs/app-2024-02.log')))->toBeFalse();
            expect(file_exists($this->tempPath('logs/app-2024-03.log')))->toBeFalse();
            expect(file_exists($this->tempPath('logs/keep.txt')))->toBeTrue();
        });

        it('succeeds when files do not exist', function (): void {
            $action = (new DeleteFile('nonexistent.txt'))
                ->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toContain('Not found');
        });

        it('handles mix of existing and missing files', function (): void {
            $this->createFile('exists.txt', 'here');

            $action = (new DeleteFile('exists.txt', 'missing.txt'))
                ->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('exists.txt')))->toBeFalse();
            expect($result->output)->toContain('exists.txt');
            expect($result->output)->toContain('Not found');
            expect($result->output)->toContain('missing.txt');
        });

        it('deletes files in nested directories', function (): void {
            $this->createFile('app/Models/Team.php', '<?php');
            $this->createFile('app/Models/User.php', '<?php');

            $action = (new DeleteFile('app/Models/Team.php'))
                ->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('app/Models/Team.php')))->toBeFalse();
            // Sibling file should be untouched
            expect(file_exists($this->tempPath('app/Models/User.php')))->toBeTrue();
        });

    });

    // -------------------------------------------------------------------
    // Describe
    // -------------------------------------------------------------------

    describe('describe', function (): void {

        it('describes a single file', function (): void {
            $action = new DeleteFile('file.txt');

            expect($action->describe())->toBe('delete file.txt');
        });

        it('describes multiple files', function (): void {
            $action = new DeleteFile('one.txt', 'two.txt', 'three.txt');

            expect($action->describe())->toBe('delete one.txt, two.txt, three.txt');
        });

        it('describes glob patterns', function (): void {
            $action = new DeleteFile('logs/*.log');

            expect($action->describe())->toBe('delete logs/*.log');
        });

    });

    // -------------------------------------------------------------------
    // Operation Type
    // -------------------------------------------------------------------

    it('reports the correct operation type', function (): void {
        $action = new DeleteFile('file.txt');

        expect($action)->toBeOperation(FileOperation::Delete);
    });

    // -------------------------------------------------------------------
    // No Rollback
    // -------------------------------------------------------------------

    it('cannot be rolled back', function (): void {
        $action = new DeleteFile('file.txt');

        expect($action->canBeRolledBack())->toBeFalse();
    });

    // -------------------------------------------------------------------
    // Direct Execution Flag
    // -------------------------------------------------------------------

    it('is a direct action', function (): void {
        $action = new DeleteFile('file.txt');

        expect($action->isDirect())->toBeTrue();
        expect($action->command())->toBeNull();
    });

    // -------------------------------------------------------------------
    // Paths
    // -------------------------------------------------------------------

    it('stores paths as an array', function (): void {
        $action = new DeleteFile('one.txt', 'two.txt');

        expect($action->paths)->toBe(['one.txt', 'two.txt']);
    });

    it('stores a single path as an array', function (): void {
        $action = new DeleteFile('file.txt');

        expect($action->paths)->toBe(['file.txt']);
    });

});
