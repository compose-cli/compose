<?php

use Compose\Actions\Modify\ModifyAction;
use Compose\Enums\ModifyOperation;
use Compose\Payloads\ModifyOperationPayload;

describe('ModifyAction', function (): void {

    // -------------------------------------------------------------------
    // PHP Class Operations
    // -------------------------------------------------------------------

    describe('PHP class operations', function (): void {

        it('adds a trait to a PHP class', function (): void {
            $php = <<<'PHP'
                <?php

                namespace App\Models;

                class User
                {
                }
                PHP;

            $this->createFile('app/Models/User.php', $php);

            $action = (new ModifyAction(
                path: 'app/Models/User.php',
                operations: [
                    new ModifyOperationPayload('add_trait', ['trait' => 'Spatie\\Permission\\Traits\\HasRoles']),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('app/Models/User.php'));
            expect($contents)->toContain('use Spatie\\Permission\\Traits\\HasRoles;');
            expect($contents)->toMatch('/^\s+use HasRoles;$/m');
        });

        it('adds a method to a PHP class', function (): void {
            $php = <<<'PHP'
                <?php

                namespace App\Models;

                class User
                {
                }
                PHP;

            $this->createFile('app/Models/User.php', $php);

            $action = (new ModifyAction(
                path: 'app/Models/User.php',
                operations: [
                    new ModifyOperationPayload('add_method', [
                        'name' => 'isAdmin',
                        'body' => 'return $this->hasRole("admin");',
                        'visibility' => 'public',
                        'returnType' => 'bool',
                    ]),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('app/Models/User.php'));
            expect($contents)->toContain('public function isAdmin(): bool');
            expect($contents)->toContain('return $this->hasRole("admin");');
        });

        it('adds a property to a PHP class', function (): void {
            $php = <<<'PHP'
                <?php

                namespace App\Models;

                class User
                {
                }
                PHP;

            $this->createFile('app/Models/User.php', $php);

            $action = (new ModifyAction(
                path: 'app/Models/User.php',
                operations: [
                    new ModifyOperationPayload('add_property', [
                        'name' => 'fillable',
                        'default' => ['name', 'email'],
                        'visibility' => 'protected',
                        'type' => null,
                    ]),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('app/Models/User.php'));
            expect($contents)->toContain('protected $fillable');
            expect($contents)->toContain("'name'");
            expect($contents)->toContain("'email'");
        });

        it('adds values to an existing array property', function (): void {
            $php = <<<'PHP'
                <?php

                namespace App\Models;

                class User
                {
                    protected $fillable = ['name', 'email'];
                }
                PHP;

            $this->createFile('app/Models/User.php', $php);

            $action = (new ModifyAction(
                path: 'app/Models/User.php',
                operations: [
                    new ModifyOperationPayload('add_to_array', [
                        'property' => 'fillable',
                        'values' => ['team_id', 'avatar'],
                    ]),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $contents = file_get_contents($this->tempPath('app/Models/User.php'));
            expect($contents)->toContain("'team_id'");
            expect($contents)->toContain("'avatar'");
            expect($contents)->toContain("'name'");
        });

        it('applies multiple PHP operations in sequence', function (): void {
            $php = <<<'PHP'
                <?php

                namespace App\Models;

                class User
                {
                }
                PHP;

            $this->createFile('app/Models/User.php', $php);

            $action = (new ModifyAction(
                path: 'app/Models/User.php',
                operations: [
                    new ModifyOperationPayload('add_trait', ['trait' => 'Spatie\\Permission\\Traits\\HasRoles']),
                    new ModifyOperationPayload('add_method', [
                        'name' => 'isAdmin',
                        'body' => 'return $this->hasRole("admin");',
                        'visibility' => 'public',
                        'returnType' => 'bool',
                    ]),
                    new ModifyOperationPayload('add_property', [
                        'name' => 'defaultPermissions',
                        'default' => ['view'],
                        'visibility' => 'protected',
                        'type' => null,
                    ]),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect($result->output)->toContain('3 operations');

            $contents = file_get_contents($this->tempPath('app/Models/User.php'));
            expect($contents)->toMatch('/^\s+use HasRoles;$/m');
            expect($contents)->toContain('function isAdmin');
            expect($contents)->toContain('$defaultPermissions');
        });

    });

    // -------------------------------------------------------------------
    // Text Operations
    // -------------------------------------------------------------------

    describe('text operations', function (): void {

        it('replaces text in a file', function (): void {
            $this->createFile('welcome.blade.php', '<h1>Laravel</h1>');

            $action = (new ModifyAction(
                path: 'welcome.blade.php',
                operations: [
                    new ModifyOperationPayload('replace', ['search' => 'Laravel', 'replace' => 'My App']),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('welcome.blade.php')))->toBe('<h1>My App</h1>');
        });

        it('inserts after a marker', function (): void {
            $this->createFile('routes.php', "// routes\nRoute::get('/');");

            $action = (new ModifyAction(
                path: 'routes.php',
                operations: [
                    new ModifyOperationPayload('insert_after', [
                        'marker' => "// routes\n",
                        'contents' => "Route::get('/dashboard');\n",
                    ]),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            $contents = file_get_contents($this->tempPath('routes.php'));
            expect($contents)->toContain("Route::get('/dashboard');");
        });

    });

    // -------------------------------------------------------------------
    // JSON Operations
    // -------------------------------------------------------------------

    describe('JSON operations', function (): void {

        it('sets a value in a JSON file', function (): void {
            $this->createFile('package.json', '{"name": "app"}');

            $action = (new ModifyAction(
                path: 'package.json',
                operations: [
                    new ModifyOperationPayload('json_set', ['key' => 'scripts.build', 'value' => 'vite build']),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $data = json_decode(file_get_contents($this->tempPath('package.json')), true);
            expect($data['scripts']['build'])->toBe('vite build');
        });

        it('merges values in a JSON file', function (): void {
            $this->createFile('package.json', '{"dependencies": {"vue": "^3.0"}}');

            $action = (new ModifyAction(
                path: 'package.json',
                operations: [
                    new ModifyOperationPayload('json_merge', [
                        'key' => 'dependencies',
                        'values' => ['axios' => '^1.0'],
                    ]),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            $data = json_decode(file_get_contents($this->tempPath('package.json')), true);
            expect($data['dependencies'])->toBe(['vue' => '^3.0', 'axios' => '^1.0']);
        });

    });

    // -------------------------------------------------------------------
    // Error Handling
    // -------------------------------------------------------------------

    describe('error handling', function (): void {

        it('fails when the file does not exist', function (): void {
            $action = (new ModifyAction(
                path: 'nonexistent.php',
                operations: [],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeFalse();
            expect($result->errorOutput)->toContain('File not found');
        });

        it('fails when PHP class ops target a non-PHP file', function (): void {
            $this->createFile('readme.md', '# Hello');

            $action = (new ModifyAction(
                path: 'readme.md',
                operations: [
                    new ModifyOperationPayload('add_trait', ['trait' => 'SoftDeletes']),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeFalse();
            expect($result->errorOutput)->toContain('PHP class operations can only be applied to .php files');
        });

        it('fails when JSON ops target a non-JSON file', function (): void {
            $this->createFile('config.yaml', 'key: value');

            $action = (new ModifyAction(
                path: 'config.yaml',
                operations: [
                    new ModifyOperationPayload('json_set', ['key' => 'name', 'value' => 'app']),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeFalse();
            expect($result->errorOutput)->toContain('JSON operations can only be applied to .json files');
        });

        it('fails when PHP file has no class', function (): void {
            $php = <<<'PHP'
                <?php

                return [
                    'key' => 'value',
                ];
                PHP;

            $this->createFile('config.php', $php);

            $action = (new ModifyAction(
                path: 'config.php',
                operations: [
                    new ModifyOperationPayload('add_trait', ['trait' => 'HasRoles']),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeFalse();
            expect($result->errorOutput)->toContain('No class found');
        });

    });

    // -------------------------------------------------------------------
    // Describe
    // -------------------------------------------------------------------

    describe('describe', function (): void {

        it('describes with operation count', function (): void {
            $action = new ModifyAction(
                path: 'app/Models/User.php',
                operations: [
                    new ModifyOperationPayload('add_trait', ['trait' => 'HasRoles']),
                    new ModifyOperationPayload('add_method', ['name' => 'isAdmin', 'body' => 'return true;']),
                ],
            );

            expect($action->describe())->toBe('modify app/Models/User.php (2 operations)');
        });

        it('uses singular for one operation', function (): void {
            $action = new ModifyAction(
                path: 'app/Models/User.php',
                operations: [
                    new ModifyOperationPayload('add_trait', ['trait' => 'HasRoles']),
                ],
            );

            expect($action->describe())->toBe('modify app/Models/User.php (1 operation)');
        });

    });

    // -------------------------------------------------------------------
    // Rollback
    // -------------------------------------------------------------------

    describe('rollback', function (): void {

        it('can be rolled back', function (): void {
            $action = new ModifyAction(path: 'file.php', operations: []);

            expect($action->canBeRolledBack())->toBeTrue();
            expect($action->canRollbackDirect())->toBeTrue();
        });

        it('restores original file contents on rollback', function (): void {
            $original = <<<'PHP'
                <?php

                namespace App\Models;

                class User
                {
                }
                PHP;

            $this->createFile('app/Models/User.php', $original);

            $action = (new ModifyAction(
                path: 'app/Models/User.php',
                operations: [
                    new ModifyOperationPayload('add_trait', ['trait' => 'Spatie\\Permission\\Traits\\HasRoles']),
                ],
            ))->withContext(context(workingDirectory: $this->tempPath));

            $action->execute(context(workingDirectory: $this->tempPath));

            $modified = file_get_contents($this->tempPath('app/Models/User.php'));
            expect($modified)->not->toBe($original);

            $result = $action->rollbackDirect(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
            expect(file_get_contents($this->tempPath('app/Models/User.php')))->toBe($original);
        });

    });

    // -------------------------------------------------------------------
    // Operation Type
    // -------------------------------------------------------------------

    it('reports the correct operation type', function (): void {
        $action = new ModifyAction(path: 'file.php', operations: []);

        expect($action)->toBeOperation(ModifyOperation::Modify);
    });

    // -------------------------------------------------------------------
    // Direct Execution Flag
    // -------------------------------------------------------------------

    it('is a direct action (no shell command)', function (): void {
        $action = new ModifyAction(path: 'file.php', operations: []);

        expect($action->isDirect())->toBeTrue();
        expect($action->command())->toBeNull();
    });

});
