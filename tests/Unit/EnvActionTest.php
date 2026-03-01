<?php

use Compose\Actions\Env\EnvAction;
use Compose\Builders\EnvBuilder;
use Compose\Enums\EnvOperation;

describe('EnvAction', function (): void {

    // -------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------

    describe('execution', function (): void {

        it('sets values in an existing env file', function (): void {
            $this->createFile('.env', "APP_NAME=Laravel\nAPP_ENV=local");

            $action = (new EnvAction(
                path: '.env',
                operations: [
                    ['type' => 'set', 'key' => 'APP_NAME', 'value' => 'My App'],
                    ['type' => 'set', 'key' => 'CACHE_DRIVER', 'value' => 'redis'],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('.env'));
            expect($contents)->toContain('APP_NAME=My App');
            expect($contents)->toContain('CACHE_DRIVER=redis');
            expect($contents)->toContain('APP_ENV=local');
        });

        it('removes values from an env file', function (): void {
            $this->createFile('.env', "APP_NAME=Laravel\nAPP_DEBUG=true\nAPP_ENV=local");

            $action = (new EnvAction(
                path: '.env',
                operations: [
                    ['type' => 'remove', 'key' => 'APP_DEBUG'],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('.env'));
            expect($contents)->not->toContain('APP_DEBUG');
            expect($contents)->toContain('APP_NAME=Laravel');
        });

        it('comments out a key', function (): void {
            $this->createFile('.env', "APP_NAME=Laravel\nAPP_DEBUG=true");

            $action = (new EnvAction(
                path: '.env',
                operations: [
                    ['type' => 'comment', 'key' => 'APP_DEBUG'],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('.env'));
            expect($contents)->toContain('# APP_DEBUG=true');
        });

        it('uncomments a key', function (): void {
            $this->createFile('.env', "APP_NAME=Laravel\n# APP_DEBUG=true");

            $action = (new EnvAction(
                path: '.env',
                operations: [
                    ['type' => 'uncomment', 'key' => 'APP_DEBUG'],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('.env'));
            expect($contents)->not->toContain('# APP_DEBUG');
            expect($contents)->toContain('APP_DEBUG=true');
        });

        it('adds a section', function (): void {
            $this->createFile('.env', 'APP_NAME=Laravel');

            $action = (new EnvAction(
                path: '.env',
                operations: [
                    ['type' => 'section', 'header' => '# Permissions', 'values' => ['TEAMS' => 'true']],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('.env'));
            expect($contents)->toContain('# Permissions');
            expect($contents)->toContain('TEAMS=true');
        });

        it('creates the file if it does not exist', function (): void {
            $action = (new EnvAction(
                path: '.env',
                operations: [
                    ['type' => 'set', 'key' => 'APP_NAME', 'value' => 'My App'],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('.env')))->toBeTrue();
            expect(file_get_contents($this->tempPath('.env')))->toBe('APP_NAME=My App');
        });

        it('handles empty operations list', function (): void {
            $this->createFile('.env', 'APP_NAME=Laravel');

            $action = (new EnvAction(
                path: '.env',
                operations: [],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('.env')))->toBe('APP_NAME=Laravel');
        });

        it('reports operation count in output', function (): void {
            $this->createFile('.env', 'APP_NAME=Laravel');

            $action = (new EnvAction(
                path: '.env',
                operations: [
                    ['type' => 'set', 'key' => 'APP_NAME', 'value' => 'My App'],
                    ['type' => 'set', 'key' => 'APP_ENV', 'value' => 'local'],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->output)->toContain('2 operations');
        });

    });

    // -------------------------------------------------------------------
    // When (Conditional)
    // -------------------------------------------------------------------

    describe('conditional execution', function (): void {

        it('applies operations when condition is true', function (): void {
            $this->createFile('.env', "APP_DEBUG=true\nAPP_NAME=Laravel");

            $builder = new EnvBuilder;
            $builder->when('APP_DEBUG', 'true', fn (EnvBuilder $e) => $e->set('TELESCOPE_ENABLED', 'true'));

            $action = (new EnvAction(
                path: '.env',
                operations: $builder->operations(),
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('.env'));
            expect($contents)->toContain('TELESCOPE_ENABLED=true');
        });

        it('skips operations when condition is false', function (): void {
            $this->createFile('.env', "APP_DEBUG=false\nAPP_NAME=Laravel");

            $builder = new EnvBuilder;
            $builder->when('APP_DEBUG', 'true', fn (EnvBuilder $e) => $e->set('TELESCOPE_ENABLED', 'true'));

            $action = (new EnvAction(
                path: '.env',
                operations: $builder->operations(),
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('.env'));
            expect($contents)->not->toContain('TELESCOPE_ENABLED');
        });

        it('sees prior operations in the same batch', function (): void {
            $this->createFile('.env', 'APP_NAME=Laravel');

            $builder = new EnvBuilder;
            $builder
                ->set('APP_DEBUG', 'true')
                ->when('APP_DEBUG', 'true', fn (EnvBuilder $e) => $e->set('TELESCOPE_ENABLED', 'true'));

            $action = (new EnvAction(
                path: '.env',
                operations: $builder->operations(),
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            $contents = file_get_contents($this->tempPath('.env'));
            expect($contents)->toContain('TELESCOPE_ENABLED=true');
        });

    });

    // -------------------------------------------------------------------
    // Describe
    // -------------------------------------------------------------------

    describe('describe', function (): void {

        it('describes with operation count', function (): void {
            $action = new EnvAction(
                path: '.env',
                operations: [
                    ['type' => 'set', 'key' => 'APP_NAME', 'value' => 'My App'],
                    ['type' => 'remove', 'key' => 'OLD_KEY'],
                ],
            );

            expect($action->describe())->toBe('env .env (2 operations)');
        });

        it('uses singular for one operation', function (): void {
            $action = new EnvAction(
                path: '.env',
                operations: [
                    ['type' => 'set', 'key' => 'APP_NAME', 'value' => 'My App'],
                ],
            );

            expect($action->describe())->toBe('env .env (1 operation)');
        });

    });

    // -------------------------------------------------------------------
    // Rollback
    // -------------------------------------------------------------------

    describe('rollback', function (): void {

        it('can be rolled back', function (): void {
            $action = new EnvAction(path: '.env', operations: []);

            expect($action->canBeRolledBack())->toBeTrue();
            expect($action->canRollbackDirect())->toBeTrue();
        });

        it('restores original file contents on rollback', function (): void {
            $original = "APP_NAME=Laravel\nAPP_ENV=local";
            $this->createFile('.env', $original);

            $action = (new EnvAction(
                path: '.env',
                operations: [
                    ['type' => 'set', 'key' => 'APP_NAME', 'value' => 'Changed'],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $action->execute(context(workingDirectory: $this->tempPath));

            expect(file_get_contents($this->tempPath('.env')))->toContain('Changed');

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('.env')))->toBe($original);
        });

        it('deletes the file on rollback if it did not exist before', function (): void {
            $action = (new EnvAction(
                path: '.env',
                operations: [
                    ['type' => 'set', 'key' => 'APP_NAME', 'value' => 'New'],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $action->execute(context(workingDirectory: $this->tempPath));

            expect(file_exists($this->tempPath('.env')))->toBeTrue();

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_exists($this->tempPath('.env')))->toBeFalse();
        });

    });

    // -------------------------------------------------------------------
    // Operation Type
    // -------------------------------------------------------------------

    it('reports the correct operation type', function (): void {
        $action = new EnvAction(path: '.env', operations: []);

        expect($action)->toBeOperation(EnvOperation::Env);
    });

    // -------------------------------------------------------------------
    // Direct Execution Flag
    // -------------------------------------------------------------------

    it('is a direct action (no shell command)', function (): void {
        $action = new EnvAction(path: '.env', operations: []);

        expect($action->isDirect())->toBeTrue();
        expect($action->command())->toBeNull();
    });

});
