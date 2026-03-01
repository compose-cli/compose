<?php

use Compose\Support\PhpFile\ConfigFileEditor;

describe('ConfigFileEditor', function (): void {

    // -------------------------------------------------------------------
    // Set
    // -------------------------------------------------------------------

    describe('set', function (): void {

        it('sets a simple top-level key', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'timezone' => 'UTC',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->set('timezone', 'America/Chicago');
            $result = $editor->render();

            expect($result)->toContain("'timezone' => 'America/Chicago'");
            expect($result)->not->toContain("'UTC'");
        });

        it('sets a boolean value', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'teams' => false,
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->set('teams', true);
            $result = $editor->render();

            expect($result)->toContain("'teams' => true");
        });

        it('sets an integer value', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'retry' => 3,
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->set('retry', 5);
            $result = $editor->render();

            expect($result)->toContain("'retry' => 5");
        });

        it('sets a null value', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'driver' => 'redis',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->set('driver', null);
            $result = $editor->render();

            expect($result)->toContain("'driver' => null");
        });

        it('adds a new key when it does not exist', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'timezone' => 'UTC',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->set('locale', 'en');
            $result = $editor->render();

            expect($result)->toContain("'timezone' => 'UTC'");
            expect($result)->toContain("'locale' => 'en'");
        });

        it('sets a nested key using dot notation', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'models' => [
                    'permission' => 'Default',
                ],
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->set('models.permission', 'App\\Models\\Permission');
            $result = $editor->render();

            expect($result)->toContain("'App\\Models\\Permission'");
            expect($result)->not->toContain("'Default'");
        });

        it('creates intermediate arrays for deeply nested keys', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'name' => 'app',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->set('cache.stores.redis', 'predis');
            $result = $editor->render();

            expect($result)->toContain("'cache'");
            expect($result)->toContain("'stores'");
            expect($result)->toContain("'redis' => 'predis'");
        });

        it('sets an array value', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'guards' => [],
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->set('guards', ['web', 'api']);
            $result = $editor->render();

            expect($result)->toContain("'web'");
            expect($result)->toContain("'api'");
        });

    });

    // -------------------------------------------------------------------
    // Remove
    // -------------------------------------------------------------------

    describe('remove', function (): void {

        it('removes a top-level key', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'timezone' => 'UTC',
                'locale' => 'en',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->remove('timezone');
            $result = $editor->render();

            expect($result)->not->toContain("'timezone'");
            expect($result)->toContain("'locale' => 'en'");
        });

        it('removes a nested key', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'models' => [
                    'permission' => 'Default',
                    'role' => 'Default',
                ],
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->remove('models.permission');
            $result = $editor->render();

            expect($result)->not->toContain("'permission'");
            expect($result)->toContain("'role' => 'Default'");
        });

        it('is a no-op when key does not exist', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'timezone' => 'UTC',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->remove('nonexistent');
            $result = $editor->render();

            expect($result)->toContain("'timezone' => 'UTC'");
        });

    });

    // -------------------------------------------------------------------
    // Merge
    // -------------------------------------------------------------------

    describe('merge', function (): void {

        it('merges values into an existing array', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'guard_names' => ['web'],
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->merge('guard_names', ['api', 'admin']);
            $result = $editor->render();

            expect($result)->toContain("'web'");
            expect($result)->toContain("'api'");
            expect($result)->toContain("'admin'");
        });

        it('skips duplicate values when merging', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'guard_names' => ['web', 'api'],
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->merge('guard_names', ['web', 'admin']);
            $result = $editor->render();

            expect($result)->toContain("'admin'");
            expect(substr_count($result, "'web'"))->toBe(1);
        });

        it('creates the array if it does not exist', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'name' => 'app',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->merge('providers', ['AuthServiceProvider']);
            $result = $editor->render();

            expect($result)->toContain("'providers'");
            expect($result)->toContain("'AuthServiceProvider'");
        });

    });

    // -------------------------------------------------------------------
    // Push
    // -------------------------------------------------------------------

    describe('push', function (): void {

        it('pushes a value onto an existing array', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'providers' => ['AppServiceProvider'],
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->push('providers', 'AuthServiceProvider');
            $result = $editor->render();

            expect($result)->toContain("'AppServiceProvider'");
            expect($result)->toContain("'AuthServiceProvider'");
        });

        it('creates the array if it does not exist', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'name' => 'app',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->push('middlewares', 'auth');
            $result = $editor->render();

            expect($result)->toContain("'middlewares'");
            expect($result)->toContain("'auth'");
        });

    });

    // -------------------------------------------------------------------
    // Comment
    // -------------------------------------------------------------------

    describe('comment', function (): void {

        it('comments out a top-level key', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'teams' => false,
                'locale' => 'en',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->comment('teams');
            $result = $editor->render();

            expect($result)->toMatch("/\/\/.*'teams'/");
            expect($result)->toContain("'locale' => 'en'");
        });

    });

    // -------------------------------------------------------------------
    // Has / Get
    // -------------------------------------------------------------------

    describe('has', function (): void {

        it('returns true for existing keys', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'timezone' => 'UTC',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);

            expect($editor->has('timezone'))->toBeTrue();
        });

        it('returns true for nested keys', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'models' => [
                    'role' => 'Default',
                ],
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);

            expect($editor->has('models.role'))->toBeTrue();
        });

        it('returns false for missing keys', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'timezone' => 'UTC',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);

            expect($editor->has('nonexistent'))->toBeFalse();
        });

    });

    describe('get', function (): void {

        it('gets a string value', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'timezone' => 'UTC',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);

            expect($editor->get('timezone'))->toBe('UTC');
        });

        it('gets a boolean value', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'teams' => true,
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);

            expect($editor->get('teams'))->toBeTrue();
        });

        it('gets a nested value', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'models' => [
                    'role' => 'DefaultRole',
                ],
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);

            expect($editor->get('models.role'))->toBe('DefaultRole');
        });

        it('gets an array value', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'guards' => ['web', 'api'],
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);

            expect($editor->get('guards'))->toBe(['web', 'api']);
        });

        it('returns null for missing keys', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'name' => 'app',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);

            expect($editor->get('missing'))->toBeNull();
        });

    });

    // -------------------------------------------------------------------
    // Error Handling
    // -------------------------------------------------------------------

    describe('error handling', function (): void {

        it('throws when file has no return statement', function (): void {
            $code = <<<'PHP'
            <?php

            $foo = 'bar';
            PHP;

            expect(fn () => ConfigFileEditor::fromCode($code))
                ->toThrow(RuntimeException::class, 'Config file must contain a return statement with an array');
        });

        it('throws when return is not an array', function (): void {
            $code = <<<'PHP'
            <?php

            return 'not an array';
            PHP;

            expect(fn () => ConfigFileEditor::fromCode($code))
                ->toThrow(RuntimeException::class, 'Config file must contain a return statement with an array');
        });

        it('throws when traversing into a non-array key', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                'driver' => 'redis',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);

            expect(fn () => $editor->set('driver.nested', 'value'))
                ->toThrow(RuntimeException::class, 'Cannot traverse into non-array key');
        });

    });

    // -------------------------------------------------------------------
    // Format Preservation
    // -------------------------------------------------------------------

    describe('format preservation', function (): void {

        it('preserves comments in the config file', function (): void {
            $code = <<<'PHP'
            <?php

            return [
                // This is the app timezone
                'timezone' => 'UTC',
                'locale' => 'en',
            ];
            PHP;

            $editor = ConfigFileEditor::fromCode($code);
            $editor->set('locale', 'fr');
            $result = $editor->render();

            expect($result)->toContain('// This is the app timezone');
            expect($result)->toContain("'locale' => 'fr'");
        });

    });

});
