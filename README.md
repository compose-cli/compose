# Compose

Declarative scaffolding for PHP projects.

Compose lets you describe a project setup as a single PHP recipe file — packages, config, file operations, artisan commands — and execute it with one command. Every action is rollbackable, every step is testable, and the recipe reads top to bottom like documentation.

## Why

Setting up a new Laravel project is a 45-minute ritual. Install packages, publish configs, modify models, set up CI, configure environment files — all stuff you've done a hundred times. Compose captures that ritual as code.

A recipe file is the `dotfiles` equivalent for PHP projects. One file, readable by anyone, shareable as a Gist, runnable in seconds.

## Installation

```bash
composer require compose/compose
```

## Quick Start

Create a `recipe.php` in your project root:

```php
<?php

use Compose\Step;
use Compose\Builders\Artisan;
use Compose\Builders\ConfigBuilder;
use Compose\Builders\ModifyBuilder;

return compose('My App')
    ->in('my-app', fresh: true)
    ->base(repo: 'https://github.com/laravel/laravel.git')
    ->commit(automatically: true)
    ->step('Auth & Permissions', function (Step $step): void {
        $step
            ->composer(install: ['laravel/fortify', 'spatie/laravel-permission'])
            ->artisan(function (Artisan $a): void {
                $a->publish(provider: 'Laravel\Fortify\FortifyServiceProvider')
                  ->publish(provider: 'Spatie\Permission\PermissionServiceProvider')
                  ->config('permission.teams', true)
                  ->config('permission', fn (ConfigBuilder $c) => $c
                      ->merge('guard_names', ['web', 'api']))
                  ->migrate();
            })
            ->modify('app/Models/User.php', fn (ModifyBuilder $m) => $m
                ->addTrait('Spatie\Permission\Traits\HasRoles')
            );
    })
    ->step('Dev Tooling', function (Step $step): void {
        $step
            ->composer(dev: ['pestphp/pest', 'laravel/pint', 'rector/rector'])
            ->node(install: ['vue', 'inertia'])
            ->node(dev: ['vite', 'tailwindcss']);
    })
    ->step('CI Pipeline', function (Step $step): void {
        $step
            ->sink(
                from: 'github:laravel/laravel@11.x:.github/workflows/tests.yml',
                to: '.github/workflows/tests.yml',
            )
            ->create('.github/workflows/deploy.yml', $deployYaml);
    })
    ->step('Build', function (Step $step): void {
        $step->node(run: 'build', allowFailure: true);
    });
```

Run it:

```bash
php compose.php compose recipe.php
```

Dry-run to preview the plan without executing:

```bash
php compose.php compose recipe.php --dry
```

## The Recipe File

A recipe is a PHP file that returns a `Compose` instance. You configure the project, then define steps. Each step receives a `Step` object with a fluent API for declaring operations.

### Project Configuration

```php
compose('My App')
    ->in('my-app', fresh: true)      // Working directory (fresh: delete first)
    ->base(repo: 'https://...')       // Clone a base repo
    ->node(Node::Pnpm)               // Use pnpm instead of npm
    ->commit(automatically: true);    // Git commit after each step
```

### Steps

Steps are the top-level units of work. Each step groups related operations, and the runner executes them sequentially. If a step fails, its actions are rolled back in reverse order.

```php
$recipe->step('Step Name', function (Step $step): void {
    // operations go here
});
```

## Step API

### Package Management

**Composer** — install, remove, and run scripts:

```php
$step
    ->composer(install: ['laravel/sanctum'])              // composer require
    ->composer(dev: ['pestphp/pest', 'laravel/pint'])     // composer require --dev
    ->composer(remove: ['laravel/breeze'])                 // composer remove
    ->composer(run: 'test', args: ['--filter=unit']);      // composer run test -- --filter=unit
```

**Node** — works with npm, yarn, pnpm, and bun:

```php
$step
    ->node(install: ['vue', 'axios'])                     // npm install
    ->node(dev: ['vite', 'tailwindcss'])                  // npm install --save-dev
    ->node(remove: ['moment'])                            // npm uninstall
    ->node(run: 'build');                                 // npm run build
```

The node package manager is set once on the recipe (`->node(Node::Pnpm)`) and all node operations use it automatically. Command verbs and flags adapt per manager.

### Artisan Commands

Single command:

```php
$step->artisan('migrate');
$step->artisan('make:model Team -mf');
$step->artisan('vendor:publish --tag=cashier-migrations');
```

Batch operations via builder:

```php
$step->artisan(function (Artisan $a): void {
    $a->migrate(fresh: true, seed: true)
      ->publish(provider: 'Laravel\Fortify\FortifyServiceProvider')
      ->makeModel('Team', migration: true, factory: true)
      ->seed('RolesAndPermissionsSeeder')
      ->run('optimize:clear');
});
```

### File Operations

All paths are relative to the working directory.

```php
$step
    ->create('config/teams.php', $configContents)    // Create file (auto-creates directories)
    ->copy('stubs/model.php', 'app/Models/Team.php') // Copy file
    ->append('.gitignore', "\n.env.testing\n")        // Append to existing file
    ->delete('README.md', 'CHANGELOG.md')            // Delete files (supports globs)
    ->read('composer.json');                          // Read file (captures output for context)
```

`create` and `copy` accept an `overwrite: false` parameter to skip existing files. `delete` silently skips missing files and supports glob patterns for batch deletion.

### Environment Files

Set `.env` values in bulk with an array:

```php
$step->env(['APP_NAME' => 'My App', 'CACHE_DRIVER' => 'redis']);
```

Or use the builder for fine-grained control:

```php
$step->env(function (EnvBuilder $env): void {
    $env->set('APP_NAME', 'My App')
        ->remove('APP_EXAMPLE')
        ->comment('REDIS_HOST')
        ->uncomment('MAIL_MAILER')
        ->after('DB_PASSWORD')->section('# Redis', [
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PORT' => '6379',
        ]);
});
```

Conditional operations are evaluated at execution time against the actual file contents:

```php
$step->env(function (EnvBuilder $env): void {
    $env->when('APP_DEBUG', 'true', fn (EnvBuilder $e) => $e
        ->set('TELESCOPE_ENABLED', 'true')
    );
});
```

`when()` has three forms: `when('KEY', 'value', fn)` checks key equals value, `when('KEY', fn)` checks key exists, and `when(fn(EnvFileParser $p) => ..., fn)` for custom conditions.

Target a different file with the `path` parameter:

```php
$step->env(['DB_DATABASE' => ':memory:'], path: '.env.testing');
```

### File Modification

Modify existing PHP classes, JSON files, or any text file:

```php
$step->modify('app/Models/User.php', function (ModifyBuilder $m): void {
    $m->addTrait('Spatie\Permission\Traits\HasRoles')
      ->addMethod('isAdmin', 'return $this->hasRole("admin");', returnType: 'bool')
      ->addProperty('defaultPermissions', ['view'], 'protected')
      ->addToArray('fillable', ['team_id', 'avatar']);
});
```

PHP class operations use [Nette PHP Generator](https://github.com/nette/php-generator) for AST-safe manipulation — no regex, no string corruption. FQCNs are automatically resolved to `use` imports.

**Available PHP class operations:**

| Method | Signature |
|---|---|
| `addTrait` | `addTrait(string $trait)` — FQCN auto-imports |
| `removeTrait` | `removeTrait(string $trait)` |
| `addInterface` | `addInterface(string $interface)` — FQCN auto-imports |
| `addImport` | `addImport(string $class)` |
| `removeImport` | `removeImport(string $class)` |
| `addMethod` | `addMethod(string $name, string $body, string $visibility = 'public', ?string $returnType = null)` |
| `addProperty` | `addProperty(string $name, mixed $default = null, string $visibility = 'public', ?string $type = null)` |
| `addConstant` | `addConstant(string $name, mixed $value, string $visibility = 'public')` |
| `addToArray` | `addToArray(string $property, array $values)` — deduplicates indexed arrays |
| `addToMethod` | `addToMethod(string $method, string $code)` — appends to method body |
| `removeMethod` | `removeMethod(string $name)` |

**Text operations** work on any file type:

```php
$step->modify('resources/views/welcome.blade.php', function (ModifyBuilder $m): void {
    $m->replace('Laravel', 'My App')
      ->insertAfter("{{-- scripts --}}\n", '<script src="app.js"></script>')
      ->append("\n{{-- footer --}}");
});
```

| Method | Signature |
|---|---|
| `replace` | `replace(string $search, string $replace)` |
| `replaceRegex` | `replaceRegex(string $pattern, string $replace)` |
| `prepend` | `prepend(string $contents)` |
| `append` | `append(string $contents)` |
| `insertAfter` | `insertAfter(string $marker, string $contents)` |
| `insertBefore` | `insertBefore(string $marker, string $contents)` |

**JSON operations** via the `json()` delegate:

```php
$step->modify('package.json', function (ModifyBuilder $m): void {
    $m->json(function (JsonModifyBuilder $j): void {
        $j->set('scripts.build', 'vite build')
          ->merge('dependencies', ['axios' => '^1.0'])
          ->remove('devDependencies.webpack')
          ->push('keywords', 'laravel');
    });
});
```

File type detection is automatic: `.php` files use AST manipulation, `.json` files use the JSON manipulator, everything else uses text operations. For `.php` files, AST operations run first, then text operations on the rendered output.

### Config File Manipulation

Modify Laravel config files (PHP arrays) with AST-safe manipulation via [nikic/php-parser](https://github.com/nikic/PHP-Parser). Config operations live on the Artisan builder since they're Laravel-specific.

**Dot-notation shorthand** — first segment is the config file name, rest is the key path:

```php
$step->artisan(function (Artisan $a): void {
    $a->config('permission.teams', true);           // config/permission.php → 'teams' => true
    $a->config('app.timezone', 'America/Chicago');   // config/app.php → 'timezone' => 'America/Chicago'
    $a->config('permission.models.role', 'App\\Models\\Role'); // nested key
});
```

**Builder form** for multiple operations on one file:

```php
$step->artisan(function (Artisan $a): void {
    $a->config('permission', fn (ConfigBuilder $c) => $c
        ->set('teams', true)
        ->set('models.role', 'App\\Models\\Role')
        ->merge('guard_names', ['web', 'api'])
        ->push('providers', 'PermissionServiceProvider')
        ->remove('cache.store')
        ->comment('display_permission_in_exception')
    );
});
```

Full file paths are also accepted: `$a->config('config/custom/myfile.php', fn (...) => ...)`.

**Available ConfigBuilder methods:**

| Method | Signature |
|---|---|
| `set` | `set(string $key, mixed $value)` — dot-notation for nested keys |
| `remove` | `remove(string $key)` — dot-notation for nested keys |
| `merge` | `merge(string $key, array $values)` — deduplicates existing values |
| `push` | `push(string $key, mixed $value)` — append to array |
| `comment` | `comment(string $key)` — comment out a key-value pair |

Config operations use the format-preserving printer, so comments and formatting in the original file are maintained. Rollback restores the original file contents.

### Remote Files (Sink)

Pull files from GitHub or any URL:

```php
$step
    ->sink(
        from: 'github:laravel/laravel@11.x:.github/workflows/tests.yml',
        to: '.github/workflows/tests.yml',
    )
    ->sink(
        from: 'github:your-org/templates@main:.editorconfig',
        // target auto-derived: .editorconfig
    )
    ->sink(
        from: 'https://example.com/config.yml',
        to: 'config/external.yml',
    );
```

GitHub shorthand format: `github:owner/repo@ref:path/to/file`. The `@ref` defaults to `main` when omitted.

### Conditional Execution

`when()` and `unless()` let you add operations conditionally without breaking the fluent chain:

```php
$step
    ->composer(install: ['laravel/framework'])
    ->when($useApi, fn (Step $s) => $s->artisan('make:controller Api/TeamController'))
    ->unless($isMinimal, fn (Step $s) => $s->composer(dev: ['laravel/telescope']));
```

Conditions can be booleans or closures. Closure conditions are evaluated at operation-resolution time, so they can defer decisions until the step actually runs:

```php
$step->when(fn () => file_exists('composer.json'), fn (Step $s) => $s
    ->composer(run: 'post-install-cmd')
);
```

`tap()` always runs its callback — useful for logging, side effects, or grouping without conditions:

```php
$step->tap(fn (Step $s) => $s
    ->create('storage/logs/.gitkeep', '')
    ->create('storage/framework/sessions/.gitkeep', '')
);
```

### Verification Gates

`verify()` and `assert()` let you check project state during execution:

```php
$step
    ->verify(fn () => file_exists('config/permission.php'))  // Warn on failure, continue
    ->assert(fn () => file_exists('composer.json'));          // Stop execution on failure
```

`verify()` is non-fatal — a falsy return logs a warning but the recipe continues. `assert()` is fatal — a falsy return stops the step and recipe immediately.

String assertions are reserved for future AI-powered verification and are currently skipped:

```php
$step->verify('The User model uses HasRoles');  // Skipped (AI not available)
```

`test()` runs test files via `php artisan test`:

```php
$step->test('tests/Feature/TeamTest.php', 'tests/Feature/UserTest.php');
```

Test failures are treated as warnings by default, so the recipe continues.

### Git

```php
$step->commit('feat: initial project setup');
```

This stages all changes (`git add -A`) and commits. When `commit(automatically: true)` is set on the recipe, the runner commits after each successful step using either the step's message or a generated message.

### Failure Handling

Actions can be marked as non-fatal:

```php
$step->node(run: 'build', allowFailure: true);
```

Steps can set a failure strategy:

```php
new Step(
    name: 'Optional setup',
    failureStrategy: FailureStrategy::Continue,
    callback: fn (Step $step) => $step->node(run: 'build'),
);
```

Available strategies: `Abort` (default), `Continue`, `Retry`, `Rollback`, `RollbackAll`.

## Execution Model

### Two-Track Execution

Compose has two kinds of actions:

**Command-based actions** (composer, node, git, artisan, sink) shell out via `symfony/process`. They build a `PendingCommand` that resolves to an array of arguments.

**Direct actions** (create, read, copy, append, delete) execute PHP-native filesystem operations. They override `execute(RecipeContext $context)` instead of `command()`.

The runner detects which track to use automatically. Both tracks produce `ActionResult` objects and support rollback.

### Rollback

Every action that can be undone defines a rollback. The `RollbackManager` tracks completed actions per step and unwinds them in LIFO order on failure.

| Action | Rollback |
|---|---|
| `ComposerInstall` | `composer remove` the packages |
| `NodeInstall` | Uninstall the packages |
| `GitClone` | Delete the cloned directory |
| `CreateFile` | Delete the file (or restore original if overwritten) |
| `CopyFile` | Delete the copy (or restore original) |
| `AppendFile` | Truncate the appended bytes |
| `Sink` | Delete the downloaded file |
| `EnvAction` | Restore original `.env` contents (or delete if created) |
| `ModifyAction` | Restore original file contents |
| `ConfigAction` | Restore original file contents |
| `DeleteFile` | No rollback (destructive) |
| `ComposerRun`, `ArtisanAction` | No rollback (side effects unknown) |

### Events

The runner dispatches events throughout execution:

```
StepStarting → ActionExecuting → ActionCompleted/ActionFailed → StepCompleted/StepFailed
```

On rollback: `RollbackStarting → RollbackCompleted`.

The CLI command wires these to `SymfonyStyle` output for colored terminal feedback.

## Testing

Compose ships with `ProcessExecutor::fake()` for testing recipes without executing real commands:

```php
use Compose\Execution\ProcessExecutor;
use Compose\Execution\ActionResult;

ProcessExecutor::fake([
    'composer *' => ActionResult::success(),
    'git clone *' => ActionResult::failure(128, 'repo not found'),
]);

// ... run your recipe or actions ...

ProcessExecutor::assertExecuted(['composer', 'require', 'laravel/framework']);
ProcessExecutor::assertNotExecuted(['npm', '*']);
ProcessExecutor::assertNothingExecuted();
```

Pattern matching supports `*` wildcards. Unmatched commands return success by default.

For file operations, use the `InteractsWithFilesystem` test trait:

```php
uses(InteractsWithFilesystem::class)
    ->beforeEach(fn () => $this->initializeTempDirectory())
    ->afterEach(fn () => $this->cleanupTempDirectory());

it('creates a file', function (): void {
    $this->createFile('existing.txt', 'original');

    $action = (new CreateFile('test.txt', 'hello'))
        ->withContext(context(workingDirectory: $this->tempPath));

    $result = $action->execute(context(workingDirectory: $this->tempPath));

    expect($result->successful)->toBeTrue();
    expect(file_get_contents($this->tempPath('test.txt')))->toBe('hello');
});
```

Run the test suite:

```bash
composer test
```

## Project Structure

```
src/
├── Compose.php                    # Recipe builder & entry point
├── Step.php                       # Fluent step builder
├── RecipeContext.php               # Execution context (binaries, working dir)
├── Filesystem.php                 # Recursive directory deletion
├── helpers.php                    # compose() and slugify() helpers
│
├── Actions/
│   ├── Action.php                 # Abstract base (command + direct execution)
│   ├── PendingCommand.php         # Fluent command builder for shell ops
│   ├── Sink.php                   # Remote file fetching (GitHub shorthand)
│   ├── Artisan/
│   │   └── ArtisanAction.php
│   ├── Config/
│   │   └── ConfigAction.php       # PHP config array modification, rollback via restore
│   ├── Env/
│   │   └── EnvAction.php          # Direct execution, rollback via restore
│   ├── Modify/
│   │   └── ModifyAction.php       # AST/JSON/text file modification, rollback via restore
│   ├── Composer/
│   │   ├── ComposerAction.php     # Abstract base
│   │   ├── ComposerInstall.php
│   │   ├── ComposerRemove.php
│   │   └── ComposerRun.php
│   ├── File/
│   │   ├── CreateFile.php         # Direct execution, rollback via delete/restore
│   │   ├── ReadFile.php           # Direct execution, no rollback
│   │   ├── CopyFile.php           # Direct execution, rollback via delete/restore
│   │   ├── AppendFile.php         # Direct execution, rollback via truncate
│   │   └── DeleteFile.php         # Direct execution, no rollback
│   ├── Git/
│   │   ├── GitAdd.php
│   │   ├── GitClone.php
│   │   ├── GitCommit.php
│   │   └── GitInit.php
│   ├── Test/
│   │   └── TestAction.php         # Run artisan test --filter (command-based)
│   ├── Verify/
│   │   └── VerifyAction.php       # Closure/string assertion (direct execution)
│   └── Node/
│       ├── NodeAction.php         # Abstract base (adapts to npm/yarn/pnpm/bun)
│       ├── NodeInstall.php
│       ├── NodeRemove.php
│       └── NodeRun.php
│
├── Builders/
│   ├── Artisan.php                # Batch artisan operations builder (+ config)
│   ├── ConfigBuilder.php          # Fluent config file manipulation builder
│   ├── EnvBuilder.php             # Fluent .env manipulation builder
│   ├── ModifyBuilder.php          # PHP class, text, and JSON modification builder
│   └── JsonModifyBuilder.php      # JSON-specific operations (set, merge, remove, push)
│
├── Contracts/
│   ├── AI.php
│   ├── CommitMessageGenerator.php
│   └── Operation.php
│
├── Enums/
│   ├── ArtisanOperation.php
│   ├── ConfigOperation.php
│   ├── EnvOperation.php
│   ├── ModifyOperation.php
│   ├── FailureStrategy.php
│   ├── FileOperation.php
│   ├── GitOperation.php
│   ├── Node.php                   # npm, yarn, pnpm, bun
│   ├── PackageOperation.php
│   ├── TaskType.php
│   └── VerifyOperation.php
│
├── Events/                        # Lifecycle events
│   ├── EventDispatcher.php
│   ├── ActionExecuting.php
│   ├── ActionCompleted.php
│   ├── ActionFailed.php
│   ├── StepStarting.php
│   ├── StepCompleted.php
│   ├── StepFailed.php
│   ├── RollbackStarting.php
│   └── RollbackCompleted.php
│
├── Execution/
│   ├── ProcessExecutor.php        # Shell execution (+ fake() for testing)
│   ├── FakeProcessExecutor.php    # Pattern-matched fake responses
│   ├── Pipeline.php               # Generic pipe pipeline
│   ├── ActionResult.php           # Command result value object
│   ├── StepResult.php
│   ├── RunResult.php
│   ├── StepContext.php            # Per-step execution state
│   ├── RecipeConfig.php           # Extracted config value object
│   ├── RollbackManager.php        # LIFO rollback tracking
│   ├── Plan.php                   # Dry-run plan renderer
│   ├── StepPlan.php
│   ├── DefaultCommitMessageGenerator.php
│   └── Pipes/
│       └── ResolveOperations.php
│
├── Payloads/
│   └── ModifyOperationPayload.php # Typed value object for modify operations
│
├── Support/
│   ├── JsonFile/
│   │   └── JsonManipulator.php    # JSON file manipulation (dot-notation)
│   ├── PhpFile/
│   │   ├── ClassManipulator.php   # Nette wrapper for class manipulation
│   │   ├── ConfigFileEditor.php   # php-parser based config array manipulation
│   │   └── PhpFileEditor.php      # Load/render PHP files via Nette AST
│   └── TextFile/
│       ├── EnvFileParser.php      # .env file parser/serializer
│       └── TextManipulator.php    # Static text operations (replace, insert, etc.)
│
├── Console/
│   └── Commands/
│       └── ComposeCommand.php     # CLI entry point
│
└── Exceptions/
    └── DangerousPathException.php
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).

---

<!-- AI-REFERENCE: Everything below this line is reference material for LLMs generating recipe files. -->

# AI Reference: Writing Compose Recipe Files

This section is structured context for language models. When a user asks you to generate a Compose recipe, use the API reference below to produce correct, idiomatic recipe files.

## Recipe File Structure

A recipe file is a PHP file that returns a `Compose` instance. It follows this structure:

```php
<?php

use Compose\Step;
use Compose\Enums\Node;
use Compose\Enums\FailureStrategy;
use Compose\Builders\Artisan;
use Compose\Builders\ConfigBuilder;
use Compose\Builders\EnvBuilder;
use Compose\Builders\ModifyBuilder;
use Compose\Builders\JsonModifyBuilder;

return compose('Project Name')
    // Configuration
    ->in('directory', fresh: true)
    ->base(repo: 'https://github.com/laravel/laravel.git', branch: '11.x')
    ->node(Node::Pnpm)
    ->commit(automatically: true, smart: false)

    // Steps
    ->step('Step Name', function (Step $step): void {
        $step->composer(install: ['package/name']);
    });
```

## Compose Configuration Methods

| Method | Signature | Purpose |
|---|---|---|
| `in` | `in(string $path, bool $fresh = false)` | Set working directory. `fresh: true` deletes it first. |
| `base` | `base(string $repo, ?string $branch = null)` | Clone a base repository as the project foundation. |
| `node` | `node(Node $manager)` | Set node package manager: `Node::Npm`, `Node::Yarn`, `Node::Pnpm`, `Node::Bun`. |
| `commit` | `commit(bool $automatically = false, bool $smart = false)` | Auto-commit after each step. `smart: true` uses AI for commit messages. |
| `step` | `step(string $name, Closure $callback): static` | Define a step. Closure receives `Step`. Returns `$this` for chaining. |
| `plan` | `plan(): Plan` | Generate a dry-run plan without executing. |
| `run` | `run(?EventDispatcher $dispatcher = null): RunResult` | Execute the recipe. |

## Step Method Reference

### `composer()`

```php
$step->composer(
    install: array|string|null,     // Packages to require
    dev: array|string|null,         // Packages to require --dev
    remove: array|string|null,      // Packages to remove
    removeDev: array|string|null,   // Dev packages to remove
    run: ?string,                   // Script to run
    args: array|string|null,        // Args for the script
    allowFailure: bool = false,     // Treat failure as warning
);
```

Multiple parameters can be combined in one call. Each non-null parameter creates a separate action.

### `node()`

Identical signature to `composer()`. Commands adapt to the configured package manager automatically.

### `artisan()`

```php
// String form — single command
$step->artisan('migrate');
$step->artisan('make:model Team -mf');

// Closure form — batch operations
$step->artisan(function (Artisan $a): void {
    $a->run('migrate:fresh --seed')
      ->makeModel('Team', migration: true, factory: true)
      ->seed('DatabaseSeeder')
      ->publish(provider: 'Some\ServiceProvider');
});
```

**Artisan Builder methods:**

| Method | Signature |
|---|---|
| `run` | `run(string $command)` |
| `migrate` | `migrate(bool $fresh = false, bool $seed = false)` |
| `seed` | `seed(string ...$seeders)` — no args runs `db:seed` |
| `publish` | `publish(?string $provider = null, ?string $tag = null)` |
| `make` | `make(string $resource, string $name)` |
| `makeModel` | `makeModel(string $name, bool $migration = false, bool $factory = false, bool $seeder = false)` |
| `config` | `config(string $fileOrDotPath, mixed $valueOrCallback = null)` — dot-notation or builder closure |
| `when` | `when(bool\|Closure $condition, Closure $callback)` |

### `sink()`

```php
$step->sink(
    from: string,          // URL or github:owner/repo@ref:path
    to: ?string = null,    // Target path (derived from source if null)
    force: bool = true,    // Overwrite existing
);
```

GitHub shorthand: `github:owner/repo@ref:path/to/file`. Omit `@ref` to default to `main`.

### File Operations

```php
$step->create(string $path, string $contents, bool $overwrite = true);
$step->read(string $path);
$step->copy(string $from, string $to, bool $overwrite = true);
$step->append(string $path, string $contents);
$step->delete(string ...$paths);  // Supports globs, silently skips missing
```

### `env()`

```php
// Array form — bulk set
$step->env(array $values, string $path = '.env');

// Builder form — fine-grained control
$step->env(Closure $callback, string $path = '.env');
```

**EnvBuilder methods:**

| Method | Signature |
|---|---|
| `set` | `set(string $key, string $value)` |
| `remove` | `remove(string $key)` |
| `comment` | `comment(string $key)` — prefix line with `#` |
| `uncomment` | `uncomment(string $key)` — remove `#` prefix |
| `merge` | `merge(array $values)` — bulk set |
| `after` | `after(string $key)` — position next set/section after this key |
| `section` | `section(string $header, array $values)` — blank line + comment + pairs |
| `when` | `when(string\|Closure, string\|Closure, ?Closure)` — conditional at execution time |

`when()` forms: `when('KEY', 'value', fn)` key equals value, `when('KEY', fn)` key exists, `when(fn(EnvFileParser) => bool, fn)` custom.

### `modify()`

```php
// PHP class modification (AST-safe via Nette PHP Generator)
$step->modify('app/Models/User.php', function (ModifyBuilder $m): void {
    $m->addTrait('Spatie\Permission\Traits\HasRoles')
      ->addMethod('isAdmin', 'return $this->hasRole("admin");', returnType: 'bool')
      ->addProperty('fillable', ['name', 'email'], 'protected')
      ->addToArray('fillable', ['team_id']);
});

// Text file modification
$step->modify('resources/views/app.blade.php', function (ModifyBuilder $m): void {
    $m->replace('Laravel', 'My App')
      ->insertAfter("</head>\n", '<script src="app.js"></script>');
});

// JSON file modification
$step->modify('package.json', function (ModifyBuilder $m): void {
    $m->json(function (JsonModifyBuilder $j): void {
        $j->set('scripts.build', 'vite build')
          ->merge('dependencies', ['axios' => '^1.0']);
    });
});
```

**ModifyBuilder PHP class methods:**

| Method | Signature |
|---|---|
| `addTrait` | `addTrait(string $trait)` — FQCN auto-imports |
| `removeTrait` | `removeTrait(string $trait)` |
| `addInterface` | `addInterface(string $interface)` — FQCN auto-imports |
| `addImport` | `addImport(string $class)` |
| `removeImport` | `removeImport(string $class)` |
| `addMethod` | `addMethod(string $name, string $body, string $visibility = 'public', ?string $returnType = null)` |
| `addProperty` | `addProperty(string $name, mixed $default = null, string $visibility = 'public', ?string $type = null)` |
| `addConstant` | `addConstant(string $name, mixed $value, string $visibility = 'public')` |
| `addToArray` | `addToArray(string $property, array $values)` — deduplicates indexed, merges associative |
| `addToMethod` | `addToMethod(string $method, string $code)` — appends to body |
| `removeMethod` | `removeMethod(string $name)` |

**ModifyBuilder text methods:**

| Method | Signature |
|---|---|
| `replace` | `replace(string $search, string $replace)` |
| `replaceRegex` | `replaceRegex(string $pattern, string $replace)` |
| `prepend` | `prepend(string $contents)` |
| `append` | `append(string $contents)` |
| `insertAfter` | `insertAfter(string $marker, string $contents)` |
| `insertBefore` | `insertBefore(string $marker, string $contents)` |
| `json` | `json(Closure $callback)` — delegates to `JsonModifyBuilder` |

**JsonModifyBuilder methods:**

| Method | Signature |
|---|---|
| `set` | `set(string $key, mixed $value)` — dot-notation keys |
| `merge` | `merge(string $key, array $values)` |
| `remove` | `remove(string $key)` — dot-notation keys |
| `push` | `push(string $key, mixed $value)` |

File type detection: `.php` → Nette AST, `.json` → JSON manipulator, everything else → text. For `.php` files, AST ops run first, then text ops on the result.

### Conditional Execution

```php
$step->when(Closure|bool $condition, Closure $callback);   // Run callback if truthy
$step->unless(Closure|bool $condition, Closure $callback);  // Run callback if falsy
$step->tap(Closure $callback);                              // Always run callback
```

Closure conditions are evaluated at operation-resolution time. The callback receives the `Step` instance. All three return `static` for chaining.

### Verification Gates

```php
$step->verify(string|Closure $assertion);  // Non-fatal: warn on falsy, continue
$step->assert(Closure $assertion);          // Fatal: stop execution on falsy
$step->test(string ...$tests);              // Non-fatal: run artisan test --filter per path
```

`verify()` with a Closure checks truthiness. With a string, defers to AI (currently skipped). `assert()` is like `verify()` but stops the recipe on failure. `test()` runs `php artisan test --filter=<path>` for each path.

### `commit()`

```php
$step->commit(?string $message = null);  // git add -A && git commit -m "..."
```

## Complete Recipe Examples

### Example 1: Laravel SaaS Starter

```php
<?php

use Compose\Step;
use Compose\Enums\Node;
use Compose\Builders\Artisan;
use Compose\Builders\ModifyBuilder;

return compose('SaaS Starter')
    ->in('saas-app', fresh: true)
    ->base(repo: 'https://github.com/laravel/laravel.git', branch: '11.x')
    ->node(Node::Pnpm)
    ->commit(automatically: true)

    ->step('Core Packages', function (Step $step): void {
        $step
            ->composer(install: [
                'laravel/cashier',
                'laravel/sanctum',
                'spatie/laravel-permission',
            ])
            ->composer(dev: [
                'pestphp/pest',
                'laravel/pint',
            ]);
    })

    ->step('Database & Models', function (Step $step): void {
        $step
            ->artisan(function (Artisan $a): void {
                $a->publish(provider: 'Spatie\Permission\PermissionServiceProvider')
                  ->publish(tag: 'cashier-migrations')
                  ->migrate()
                  ->makeModel('Team', migration: true, factory: true)
                  ->makeModel('Subscription', migration: true);
            })
            ->modify('app/Models/User.php', function (ModifyBuilder $m): void {
                $m->addTrait('Spatie\Permission\Traits\HasRoles')
                  ->addToArray('fillable', ['team_id']);
            });
    })

    ->step('Frontend', function (Step $step): void {
        $step
            ->node(install: ['vue', '@inertiajs/vue3'])
            ->node(dev: ['vite', '@vitejs/plugin-vue', 'tailwindcss'])
            ->node(run: 'build', allowFailure: true);
    })

    ->step('CI & Config', function (Step $step): void {
        $step
            ->sink(
                from: 'github:laravel/laravel@11.x:.github/workflows/tests.yml',
                to: '.github/workflows/tests.yml',
            )
            ->create('.env.testing', <<<'ENV'
                APP_ENV=testing
                DB_CONNECTION=sqlite
                DB_DATABASE=:memory:
                ENV)
            ->delete('README.md');
    });
```

### Example 2: API-Only Project

```php
<?php

use Compose\Step;

return compose('API Service')
    ->in('api-service', fresh: true)
    ->base(repo: 'https://github.com/laravel/laravel.git')
    ->commit(automatically: true)

    ->step('Strip Frontend', function (Step $step): void {
        $step
            ->delete(
                'resources/js',
                'resources/css',
                'vite.config.js',
                'package.json',
            )
            ->composer(remove: ['laravel/vite-plugin']);
    })

    ->step('API Packages', function (Step $step): void {
        $step
            ->composer(install: [
                'laravel/sanctum',
                'spatie/laravel-query-builder',
                'spatie/laravel-data',
            ])
            ->artisan('vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"')
            ->artisan('migrate');
    })

    ->step('API Structure', function (Step $step): void {
        $step
            ->create('app/Http/Controllers/Api/V1/.gitkeep', '')
            ->create('app/Data/.gitkeep', '')
            ->create('app/Actions/.gitkeep', '');
    });
```

## Rules for Generating Recipes

1. **Always return a `Compose` instance.** The file must `return compose(...)`.

2. **Use named parameters.** `composer(install: ['pkg'])` not `composer(['pkg'])`.

3. **Group related operations into steps.** Each step should represent a logical unit — "Auth", "Frontend", "CI", not "Install Package 1", "Install Package 2".

4. **Use `allowFailure: true` for non-critical commands** like `node(run: 'build')` that might fail on a fresh project.

5. **Prefer `artisan()` with a builder for multiple artisan commands** rather than chaining several `->artisan('...')` calls.

6. **Use `sink()` for remote files, `create()` for inline content.** Don't use `sink()` when you can write the file contents directly.

7. **Paths are always relative to the working directory.** Never use absolute paths.

8. **The `composer()` and `node()` methods accept multiple parameters per call.** You can install and run in the same call: `->composer(install: ['pkg'], run: 'setup')`.

9. **Package arrays accept strings or arrays.** `composer(install: 'single/package')` works as well as `composer(install: ['a', 'b'])`.

10. **Use `modify()` for PHP class changes instead of string manipulation.** `modify()` uses AST manipulation, so `addTrait`, `addMethod`, etc. are safe. FQCNs passed to `addTrait` and `addInterface` are automatically added as `use` imports.

11. **Every action is rollbackable where possible.** Installs roll back to removes, file creates roll back to deletes, appends roll back to truncation, env and modify changes restore original contents. Artisan commands and script runs cannot be rolled back.

## Action Types and Their Operation Enums

| Action | Operation Enum | Values |
|---|---|---|
| `ComposerInstall/Remove` | `PackageOperation` | `Install`, `InstallDev`, `Remove`, `RemoveDev`, `Run` |
| `NodeInstall/Remove/Run` | `PackageOperation` | Same as above |
| `ArtisanAction` | `ArtisanOperation` | `Run`, `Make` |
| `ConfigAction` | `ConfigOperation` | `Config` |
| `CreateFile/ReadFile/...` | `FileOperation` | `Create`, `Read`, `Delete`, `Copy`, `Append`, `Sink` |
| `EnvAction` | `EnvOperation` | `Env` |
| `ModifyAction` | `ModifyOperation` | `Modify` |
| `GitAdd/Clone/Commit/Init` | `GitOperation` | `Clone`, `Init`, `Add`, `Commit` |
| `VerifyAction` | `VerifyOperation` | `Verify` |
| `TestAction` | `VerifyOperation` | `Test` |

## Node Manager Behavior

The configured `Node` enum controls command generation:

| Operation | npm | yarn | pnpm | bun |
|---|---|---|---|---|
| Install | `npm install pkg` | `yarn add pkg` | `pnpm add pkg` | `bun add pkg` |
| Install dev | `npm install --save-dev` | `yarn add --dev` | `pnpm add --save-dev` | `bun add --dev` |
| Remove | `npm uninstall` | `yarn remove` | `pnpm remove` | `bun remove` |
| Run script | `npm run script` | `yarn script` | `pnpm run script` | `bun script` |
| Args separator | `--` | none | `--` | none |

## RecipeContext

Every action receives context containing binary paths and working directory:

```php
new RecipeContext(
    phpBinary: 'php',           // Used by artisan commands
    composerBinary: 'composer', // Used by composer commands
    gitBinary: 'git',           // Used by git commands
    nodeManager: Node::Npm,     // Used by node commands
    workingDirectory: '/path',  // Resolved path for file operations
);
```

Actions access context via `$this->context()` after `withContext()` is called by the runner.