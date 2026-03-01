<?php

use Compose\Actions\File\ReadFile;
use Compose\Enums\FileOperation;

describe('ReadFile', function (): void {

    // -------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------

    describe('execution', function (): void {

        it('reads file contents', function (): void {
            $this->createFile('readme.md', '# Hello World');

            $action = (new ReadFile(
                path: 'readme.md',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toBe('# Hello World');
        });

        it('reads multi-line files', function (): void {
            $contents = "line one\nline two\nline three";
            $this->createFile('multi.txt', $contents);

            $action = (new ReadFile(
                path: 'multi.txt',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toBe($contents);
        });

        it('reads empty files', function (): void {
            $this->createFile('empty.txt', '');

            $action = (new ReadFile(
                path: 'empty.txt',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toBe('');
        });

        it('reads files in nested directories', function (): void {
            $this->createFile('app/Models/User.php', '<?php class User {}');

            $action = (new ReadFile(
                path: 'app/Models/User.php',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toBe('<?php class User {}');
        });

        it('fails when file does not exist', function (): void {
            $action = (new ReadFile(
                path: 'nonexistent.txt',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeFalse();
            expect($result->errorOutput)->toContain('not found');
        });

        it('reads binary-safe content', function (): void {
            $binary = chr(0).chr(255).chr(128);
            $this->createFile('binary.bin', $binary);

            $action = (new ReadFile(
                path: 'binary.bin',
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toBe($binary);
        });

    });

    // -------------------------------------------------------------------
    // Describe
    // -------------------------------------------------------------------

    it('describes with file path', function (): void {
        $action = new ReadFile(path: 'app/Models/User.php');

        expect($action->describe())->toBe('read app/Models/User.php');
    });

    // -------------------------------------------------------------------
    // Operation Type
    // -------------------------------------------------------------------

    it('reports the correct operation type', function (): void {
        $action = new ReadFile(path: 'file.txt');

        expect($action)->toBeOperation(FileOperation::Read);
    });

    // -------------------------------------------------------------------
    // No Rollback
    // -------------------------------------------------------------------

    it('cannot be rolled back', function (): void {
        $action = new ReadFile(path: 'file.txt');

        expect($action->canBeRolledBack())->toBeFalse();
    });

    // -------------------------------------------------------------------
    // Direct Execution Flag
    // -------------------------------------------------------------------

    it('is a direct action', function (): void {
        $action = new ReadFile(path: 'file.txt');

        expect($action->isDirect())->toBeTrue();
        expect($action->command())->toBeNull();
    });

});
