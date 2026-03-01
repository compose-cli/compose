<?php

use Compose\Actions\File\AppendFile;
use Compose\Enums\FileOperation;

describe('AppendFile', function (): void {

    // -------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------

    describe('execution', function (): void {

        it('appends content to an existing file', function (): void {
            $this->createFile('log.txt', 'line one');

            $action = (new AppendFile(
                path: 'log.txt',
                contents: "\nline two",
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('log.txt')))->toBe("line one\nline two");
        });

        it('appends multi-line content', function (): void {
            $this->createFile('routes.php', "<?php\n");

            $append = "\nRoute::get('/teams', TeamController::class);\nRoute::get('/users', UserController::class);\n";

            $action = (new AppendFile(
                path: 'routes.php',
                contents: $append,
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('routes.php')))->toBe("<?php\n".$append);
        });

        it('fails when file does not exist', function (): void {
            $action = (new AppendFile(
                path: 'nonexistent.txt',
                contents: 'data',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeFalse();
            expect($result->errorOutput)->toContain('not found');
        });

        it('appends to an empty file', function (): void {
            $this->createFile('empty.txt', '');

            $action = (new AppendFile(
                path: 'empty.txt',
                contents: 'first content',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('empty.txt')))->toBe('first content');
        });

        it('appends to files in nested directories', function (): void {
            $this->createFile('config/routes/api.php', '<?php');

            $action = (new AppendFile(
                path: 'config/routes/api.php',
                contents: "\n// appended",
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('config/routes/api.php')))->toBe("<?php\n// appended");
        });

        it('reports byte count in output', function (): void {
            $this->createFile('file.txt', 'existing');

            $action = (new AppendFile(
                path: 'file.txt',
                contents: '12345',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->output)->toContain('5 bytes');
        });

    });

    // -------------------------------------------------------------------
    // Describe
    // -------------------------------------------------------------------

    describe('describe', function (): void {

        it('describes with path and byte count', function (): void {
            $action = new AppendFile(path: 'routes/api.php', contents: 'hello');

            expect($action->describe())->toBe('append to routes/api.php (5 bytes)');
        });

        it('describes empty append', function (): void {
            $action = new AppendFile(path: 'file.txt', contents: '');

            expect($action->describe())->toBe('append to file.txt (0 bytes)');
        });

    });

    // -------------------------------------------------------------------
    // Rollback
    // -------------------------------------------------------------------

    describe('rollback', function (): void {

        it('can be rolled back', function (): void {
            $action = new AppendFile(path: 'file.txt', contents: 'data');

            expect($action->canBeRolledBack())->toBeTrue();
            expect($action->canRollbackDirect())->toBeTrue();
        });

        it('truncates appended bytes on rollback', function (): void {
            $this->createFile('file.txt', 'original');

            $action = (new AppendFile(
                path: 'file.txt',
                contents: ' appended',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $action->execute(context(workingDirectory: $this->tempPath));

            expect(file_get_contents($this->tempPath('file.txt')))->toBe('original appended');

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('file.txt')))->toBe('original');
        });

        it('handles rollback when nothing was appended', function (): void {
            $action = (new AppendFile(
                path: 'file.txt',
                contents: 'data',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toContain('Nothing to rollback');
        });

        it('handles rollback when file was already removed', function (): void {
            $this->createFile('file.txt', 'data');

            $action = (new AppendFile(
                path: 'file.txt',
                contents: ' more',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $action->execute(context(workingDirectory: $this->tempPath));

            unlink($this->tempPath('file.txt'));

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toContain('already removed');
        });

    });

    // -------------------------------------------------------------------
    // Operation Type
    // -------------------------------------------------------------------

    it('reports the correct operation type', function (): void {
        $action = new AppendFile(path: 'file.txt', contents: '');

        expect($action)->toBeOperation(FileOperation::Append);
    });

    // -------------------------------------------------------------------
    // Direct Execution Flag
    // -------------------------------------------------------------------

    it('is a direct action (no shell command)', function (): void {
        $action = new AppendFile(path: 'file.txt', contents: '');

        expect($action->isDirect())->toBeTrue();
        expect($action->command())->toBeNull();
    });

});
