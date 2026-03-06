<?php

use Compose\Actions\Config\ConfigAction;
use Compose\Builders\Artisan;
use Compose\Builders\ConfigBuilder;
use Compose\Enums\ConfigOperation;

describe('ConfigAction', function (): void {

    // -------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------

    describe('execution', function (): void {

        it('sets a value in a config file', function (): void {
            $this->createFile('config/permission.php', <<<'PHP'
            <?php

            return [
                'teams' => false,
            ];
            PHP);

            $action = (new ConfigAction(
                path: 'config/permission.php',
                operations: [
                    ['type' => 'set', 'key' => 'teams', 'value' => true],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('config/permission.php'));
            expect($contents)->toContain("'teams' => true");
        });

        it('removes a value from a config file', function (): void {
            $this->createFile('config/app.php', <<<'PHP'
            <?php

            return [
                'timezone' => 'UTC',
                'locale' => 'en',
            ];
            PHP);

            $action = (new ConfigAction(
                path: 'config/app.php',
                operations: [
                    ['type' => 'remove', 'key' => 'timezone'],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('config/app.php'));
            expect($contents)->not->toContain("'timezone'");
            expect($contents)->toContain("'locale' => 'en'");
        });

        it('merges values into an array key', function (): void {
            $this->createFile('config/permission.php', <<<'PHP'
            <?php

            return [
                'guard_names' => ['web'],
            ];
            PHP);

            $action = (new ConfigAction(
                path: 'config/permission.php',
                operations: [
                    ['type' => 'merge', 'key' => 'guard_names', 'value' => ['api', 'admin']],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('config/permission.php'));
            expect($contents)->toContain("'web'");
            expect($contents)->toContain("'api'");
            expect($contents)->toContain("'admin'");
        });

        it('pushes a value onto an array key', function (): void {
            $this->createFile('config/app.php', <<<'PHP'
            <?php

            return [
                'providers' => ['AppServiceProvider'],
            ];
            PHP);

            $action = (new ConfigAction(
                path: 'config/app.php',
                operations: [
                    ['type' => 'push', 'key' => 'providers', 'value' => 'AuthServiceProvider'],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('config/app.php'));
            expect($contents)->toContain("'AppServiceProvider'");
            expect($contents)->toContain("'AuthServiceProvider'");
        });

        it('applies multiple operations in a single action', function (): void {
            $this->createFile('config/permission.php', <<<'PHP'
            <?php

            return [
                'teams' => false,
                'guard_names' => ['web'],
                'cache' => [
                    'store' => 'default',
                ],
            ];
            PHP);

            $action = (new ConfigAction(
                path: 'config/permission.php',
                operations: [
                    ['type' => 'set', 'key' => 'teams', 'value' => true],
                    ['type' => 'merge', 'key' => 'guard_names', 'value' => ['api']],
                    ['type' => 'remove', 'key' => 'cache'],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toContain('3 operations');

            $contents = file_get_contents($this->tempPath('config/permission.php'));
            expect($contents)->toContain("'teams' => true");
            expect($contents)->toContain("'api'");
            expect($contents)->not->toContain("'cache'");
        });

        it('returns failure when file does not exist', function (): void {
            $action = (new ConfigAction(
                path: 'config/missing.php',
                operations: [
                    ['type' => 'set', 'key' => 'teams', 'value' => true],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeFalse();
            expect($result->errorOutput)->toContain('Config file not found');
        });

    });

    // -------------------------------------------------------------------
    // Describe
    // -------------------------------------------------------------------

    describe('describe', function (): void {

        it('returns the operation type', function (): void {
            $action = new ConfigAction(path: 'config/app.php', operations: []);

            expect($action)->toBeOperation(ConfigOperation::Config);
        });

        it('is a direct action', function (): void {
            $action = new ConfigAction(path: 'config/app.php', operations: []);

            expect($action->isDirect())->toBeTrue();
            expect($action->command())->toBeNull();
        });

        it('describes with operation count', function (): void {
            $action = new ConfigAction(
                path: 'config/permission.php',
                operations: [
                    ['type' => 'set', 'key' => 'teams', 'value' => true],
                    ['type' => 'set', 'key' => 'locale', 'value' => 'en'],
                ],
            );

            expect($action->describe())->toBe('config config/permission.php (2 operations)');
        });

        it('describes singular operation correctly', function (): void {
            $action = new ConfigAction(
                path: 'config/app.php',
                operations: [
                    ['type' => 'set', 'key' => 'teams', 'value' => true],
                ],
            );

            expect($action->describe())->toBe('config config/app.php (1 operation)');
        });

    });

    // -------------------------------------------------------------------
    // Rollback
    // -------------------------------------------------------------------

    describe('rollback', function (): void {

        it('restores original file contents on rollback', function (): void {
            $original = <<<'PHP'
            <?php

            return [
                'teams' => false,
            ];
            PHP;

            $this->createFile('config/permission.php', $original);

            $action = (new ConfigAction(
                path: 'config/permission.php',
                operations: [
                    ['type' => 'set', 'key' => 'teams', 'value' => true],
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $action->execute(context(workingDirectory: $this->tempPath));

            $contents = file_get_contents($this->tempPath('config/permission.php'));
            expect($contents)->toContain("'teams' => true");

            $rollbackResult = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($rollbackResult->successful)->toBeTrue();

            $restored = file_get_contents($this->tempPath('config/permission.php'));
            expect($restored)->toBe($original);
        });

        it('reports nothing to restore when file did not exist', function (): void {
            $action = (new ConfigAction(
                path: 'config/missing.php',
                operations: [],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toContain('Nothing to restore');
        });

        it('can be rolled back', function (): void {
            $action = new ConfigAction(path: 'config/app.php', operations: []);

            expect($action->canRollbackDirect())->toBeTrue();
            expect($action->canBeRolledBack())->toBeTrue();
        });

    });

});

// -------------------------------------------------------------------
// Artisan Builder Integration
// -------------------------------------------------------------------

describe('Artisan::config()', function (): void {

    it('creates a ConfigAction from dot-notation shorthand', function (): void {
        $builder = new Artisan;
        $builder->config('permission.teams', true);

        $actions = $builder->actions();

        expect($actions)->toHaveCount(1);
        expect($actions[0])->toBeInstanceOf(ConfigAction::class);
        expect($actions[0]->path)->toBe('config/permission.php');
        expect($actions[0]->operations)->toHaveCount(1);
        expect($actions[0]->operations[0]['type'])->toBe('set');
        expect($actions[0]->operations[0]['key'])->toBe('teams');
        expect($actions[0]->operations[0]['value'])->toBeTrue();
    });

    it('resolves nested dot-notation keys', function (): void {
        $builder = new Artisan;
        $builder->config('permission.models.role', 'App\\Models\\Role');

        $actions = $builder->actions();

        expect($actions[0]->path)->toBe('config/permission.php');
        expect($actions[0]->operations[0]['key'])->toBe('models.role');
    });

    it('creates a ConfigAction from builder closure', function (): void {
        $builder = new Artisan;
        $builder->config('permission', fn (ConfigBuilder $c) => $c
            ->set('teams', true)
            ->merge('guard_names', ['web', 'api']),
        );

        $actions = $builder->actions();

        expect($actions)->toHaveCount(1);
        expect($actions[0])->toBeInstanceOf(ConfigAction::class);
        expect($actions[0]->path)->toBe('config/permission.php');
        expect($actions[0]->operations)->toHaveCount(2);
    });

    it('accepts full file paths', function (): void {
        $builder = new Artisan;
        $builder->config('config/custom/myconfig.php', fn (ConfigBuilder $c) => $c
            ->set('key', 'value'),
        );

        $actions = $builder->actions();

        expect($actions[0]->path)->toBe('config/custom/myconfig.php');
    });

    it('preserves ordering with artisan commands', function (): void {
        $builder = new Artisan;
        $builder
            ->publish(provider: \Spatie\Permission\PermissionServiceProvider::class)
            ->config('permission.teams', true)
            ->migrate();

        $actions = $builder->actions();

        expect($actions)->toHaveCount(3);
        expect($actions[0])->toBeInstanceOf(\Compose\Actions\Artisan\ArtisanAction::class);
        expect($actions[1])->toBeInstanceOf(ConfigAction::class);
        expect($actions[2])->toBeInstanceOf(\Compose\Actions\Artisan\ArtisanAction::class);
    });

    it('throws for single-segment dot-notation', function (): void {
        $builder = new Artisan;

        expect(fn () => $builder->config('permission', 'value'))
            ->toThrow(InvalidArgumentException::class, 'at least two segments');
    });

});
