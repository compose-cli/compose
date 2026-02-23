# Compose — Planned Changes & Refactors

This document tracks all planned refactors, fixes, and new features for Compose, organized by priority. Each item includes the rationale and a checklist of subtasks.

---

## Priority 1 and 2 tasks complete

## Priority 3: New Features — Deterministic Actions

These extend the `Step` API with deterministic operations. Each is a new Action class + Step method + tests. They're listed roughly in order of implementation difficulty and user value.





### 3.2 Laravel Helper Shortcuts

**Why:** `migrate()`, `seed()`, `publish()`, and `makeModel()` are thin wrappers around `artisan()` that improve readability. They're trivial to implement once Artisan exists.

```php
$step->migrate(fresh: true, seed: true);
$step->seed('RolesSeeder', 'TeamSeeder');
$step->publish(provider: 'Spatie\Permission\PermissionServiceProvider');
$step->publish(tag: 'permission-migrations');
$step->makeModel('Team', migration: true, factory: true, seeder: true);
```

- [ ] Add `Step::migrate(bool $fresh = false, bool $seed = false): static`
- [ ] Add `Step::seed(string ...$seeders): static`
- [ ] Add `Step::publish(?string $provider = null, ?string $tag = null): static`
- [ ] Add `Step::makeModel(string $name, bool $migration = false, bool $factory = false, bool $seeder = false): static`
- [ ] Each method creates the appropriate `ArtisanAction` internally
- [ ] Add tests for each shortcut with various parameter combinations
- [ ] Test that `publish()` with no args infers from the last composer install (stretch goal)

### 3.3 File Operations

**Why:** Creating, copying, and deleting files are fundamental scaffolding operations. These don't need process execution — they're filesystem operations.

```php
$step->create('config/hub.php', $contents);
$step->copy('stubs/welcome.blade.php', 'resources/views/welcome.blade.php');
$step->append('routes/api.php', "\nRoute::apiResource('teams', TeamController::class);");
$step->delete('.styleci.yml', 'webpack.mix.js');
```

- [ ] Create `src/Actions/File/CreateFileAction.php`
- [ ] Create `src/Actions/File/CopyFileAction.php`
- [ ] Create `src/Actions/File/DeleteFileAction.php`
- [ ] Create `src/Actions/File/AppendFileAction.php`
- [ ] Create `Compose\Enums\FileOperation` enum (Create, Copy, Delete, Append)
- [ ] These actions should NOT use `ProcessExecutor` — they're direct filesystem operations
- [ ] The `Action` interface may need to evolve: currently `command()` returns a `PendingCommand`, but file operations don't have commands. Options:
  - Add an `execute(ExecutionContext)` method to Action (preferred — aligns with the architecture we designed)
  - Or make file actions return a `PendingCommand` that wraps `cp`, `rm`, etc. (simpler but less clean)
- [ ] Rollback: `CreateFileAction` → delete the file. `DeleteFileAction` → cannot rollback (or stash contents). `CopyFileAction` → delete the copy. `AppendFileAction` → store original content, restore on rollback.
- [ ] Add `Step::create()`, `Step::copy()`, `Step::delete()`, `Step::append()` methods
- [ ] Add tests with `InteractsWithFilesystem` trait for actual file operations
- [ ] Resolve: paths should be relative to working directory. Absolute paths should be rejected or handled carefully.

### 3.4 Environment File Manipulation

**Why:** `.env` files are a core part of every Laravel project setup. The API supports both bulk array and detailed builder forms.

```php
// Bulk
$step->env(['APP_NAME' => 'My App', 'CACHE_DRIVER' => 'redis']);

// Builder
$step->env(fn (Env $env) => $env
    ->set('APP_NAME', 'My App')
    ->remove('APP_EXAMPLE')
    ->comment('REDIS_HOST')
    ->section('# Permissions', ['TEAMS' => 'true'])
);
```

- [ ] Create `src/Actions/Env/EnvAction.php` — takes an array of operations
- [ ] Create `src/Builders/EnvBuilder.php` with `set()`, `get()`, `has()`, `remove()`, `comment()`, `uncomment()`, `merge()`, `replace()`, `after()`, `section()`
- [ ] Create `src/Support/TextFile/EnvFileParser.php` — reads/writes `.env` files
- [ ] This is a filesystem action, not a process action (same consideration as 3.3)
- [ ] Add `Step::env(array|Closure $values): static`
- [ ] When array: bulk `set()` for each key-value pair
- [ ] When Closure: receive `EnvBuilder`, compile to operations
- [ ] Rollback: store original `.env` contents, restore on rollback
- [ ] Add tests for each builder method
- [ ] Add tests for `.env` file parsing edge cases (comments, empty lines, quoted values, multiline)

### 3.5 Config File Manipulation

**Why:** Laravel config files are PHP arrays. Modifying them requires either AST manipulation or smart string operations. Dot-notation shorthand makes common cases one-liners.

```php
$step->config('permission.teams', true);
$step->config('app.timezone', 'America/Chicago');
$step->config('permission', fn (Config $c) => $c
    ->set('teams', true)
    ->merge('guard_names', ['web', 'api'])
);
```

- [ ] Create `src/Actions/Config/ConfigAction.php`
- [ ] Create `src/Builders/ConfigBuilder.php` with `set()`, `get()`, `has()`, `remove()`, `merge()`, `push()`, `comment()`
- [ ] Create `src/Support/PhpFile/ConfigFileEditor.php` — reads/writes Laravel config files
- [ ] Approach options: (a) regex-based for simple key-value sets, (b) Nette PHP Generator for full AST, (c) hybrid
- [ ] Dot-notation shorthand: `config('permission.teams', true)` → find `config/permission.php`, set `teams` key
- [ ] Add `Step::config(string $fileOrDotPath, mixed $value = null)` and `Step::config(string $file, Closure $callback)` overloads
- [ ] Add tests for shorthand, bulk, and builder forms
- [ ] Add tests for nested keys, arrays, and edge cases

---

## Priority 4: Modify — The Core Feature

This is the differentiating feature of Compose. It uses Nette PHP Generator to perform AST-safe modifications to PHP classes.

### 4.1 Modify Builder & Nette Integration

**Why:** The `modify()` method is what makes Compose more than a shell script wrapper. It lets users add traits, methods, properties, and imports to PHP classes without risking file corruption. Nette PHP Generator provides the AST manipulation engine.

```php
$step->modify('app/Models/User.php', fn (Modify $m) => $m
    ->addTrait('Spatie\Permission\Traits\HasRoles')
    ->addMethod('isAdmin', 'return $this->hasRole("admin");')
    ->addProperty('defaultPermissions', ['view'], 'protected')
    ->addToArray('fillable', ['team_id', 'avatar'])
);
```

- [ ] Create `src/Builders/ModifyBuilder.php` with all class manipulation methods:
  - `addTrait(string $trait)` — FQCN auto-resolves import
  - `addImport(string $class)`
  - `addInterface(string $interface)` — FQCN auto-resolves import
  - `addMethod(string $name, string $body)`
  - `addProperty(string $name, mixed $default, string $visibility)`
  - `addConstant(string $name, mixed $value, string $visibility)`
  - `addToArray(string $property, array $values)`
  - `addToMethod(string $method, string $code)`
  - `removeMethod(string $name)`
  - `removeTrait(string $trait)`
  - `removeImport(string $class)`
- [ ] Create `src/Builders/JsonModifyBuilder.php` with `set()`, `merge()`, `remove()`, `push()`
- [ ] Add text manipulation methods to `ModifyBuilder`:
  - `replace(string $search, string $replace)`
  - `replaceRegex(string $pattern, string $replace)`
  - `prepend(string $contents)`
  - `append(string $contents)`
  - `insertAfter(string $marker, string $contents)`
  - `insertBefore(string $marker, string $contents)`
  - `json(Closure $callback)` — delegates to `JsonModifyBuilder`
- [ ] Create `src/Support/PhpFile/PhpFileEditor.php` — load, manipulate, save using Nette
- [ ] Create `src/Support/PhpFile/ClassManipulator.php` — wraps Nette operations
- [ ] Create `src/Support/PhpFile/FileWriter.php` — PsrPrinter wrapper with formatting config
- [ ] Create `src/Support/TextFile/TextManipulator.php` — non-PHP text operations
- [ ] Create `src/Support/JsonFile/JsonManipulator.php` — JSON file operations
- [ ] Create `src/Actions/Modify/ModifyAction.php` — takes file path + compiled operations
- [ ] Create `src/Payloads/ModifyOperationPayload.php` — typed value object for each operation
- [ ] Create `src/Enums/ModifyOperation.php` enum
- [ ] Add `Step::modify(string $file, Closure $callback): static`
- [ ] File type detection: `.php` → Nette AST, `.json` → JSON manipulator, everything else → text manipulator
- [ ] FQCN auto-import resolution: when `addTrait('Spatie\Permission\Traits\HasRoles')` is called, automatically add the `use` import and use the short name for the trait declaration
- [ ] Rollback: store original file contents before modification, restore on rollback
- [ ] Add unit tests for `ClassManipulator` using Nette's `PhpFile::fromCode()` (no filesystem needed)
- [ ] Add unit tests for `TextManipulator`
- [ ] Add unit tests for `JsonManipulator`
- [ ] Add integration tests for `ModifyAction` with `InteractsWithFilesystem`
- [ ] Add tests for FQCN auto-import resolution
- [ ] Add test: modifying a non-existent file throws a clear error

---

## Priority 5: Recipe System

### 5.1 Recipe Base Class & `->use()`

**Why:** Recipes are reusable, class-based step definitions. Package authors ship them alongside their packages. Users consume them with `->use()`. This is the "Controller" to `compose.php`'s "routes file."

```php
class Permissions extends Recipe
{
    public function compose(Step $step): void
    {
        $step->composer(install: ['spatie/laravel-permission'], publish: true);
        // ...
    }
}

// Usage
compose('My App')->use(Permissions::withRoles('admin', 'editor'));
```

- [ ] Create `src/Recipe.php` abstract class with:
  - `abstract public function compose(Step $step): void`
  - `public function name(): string` (defaults to class name)
  - `public function description(): string`
  - `public function requires(): array` (dependency resolution)
  - `public function before(Step $step): void`
  - `public function after(Step $step): void`
- [ ] Add `Compose::use(Recipe|string ...$recipes): static`
- [ ] When passed a class string, instantiate it
- [ ] When passed an instance (e.g., from a static factory), use it directly
- [ ] Each Recipe becomes a Step internally — recipe's `compose()` is the step callback
- [ ] Resolve `requires()` dependencies: topological sort, run dependencies first
- [ ] Detect circular dependencies and throw
- [ ] Recipes and inline steps can be interleaved and execute in declaration order
- [ ] Add tests for: basic recipe, recipe with dependencies, recipe with static factory, mixed inline + recipe

---

## Priority 6: Control Flow & Verification

### 6.1 Conditional Execution

**Why:** `when()` and `unless()` on Step allow conditional operations without breaking the fluent chain.

```php
$step->when($useApi, fn (Step $s) => $s->artisan('make:controller Api/TeamController'))
     ->unless($isMinimal, fn (Step $s) => $s->composer(dev: ['laravel/telescope']));
```

- [ ] Add `Step::when(Closure|bool $condition, Closure $callback): static`
- [ ] Add `Step::unless(Closure|bool $condition, Closure $callback): static`
- [ ] When condition is a Closure, resolve it at operation-resolution time (deferred)
- [ ] Add `Step::tap(Closure $callback): static` for side effects
- [ ] Add tests for boolean conditions, closure conditions, and chaining

### 6.2 Verification Gates

**Why:** `verify()` and `test()` let users assert project state before proceeding. Failed verification stops execution.

```php
$step->verify(fn () => file_exists('config/permission.php'));
$step->verify('The User model uses HasRoles'); // AI-powered (later)
$step->test('tests/Feature/TeamTest.php');
```

- [ ] Create `src/Actions/Verify/VerifyAction.php` — takes a Closure, runs it, fails if falsy
- [ ] Create `src/Actions/Test/TestAction.php` — runs `php artisan test --filter=<file>`
- [ ] Add `Step::verify(string|Closure $assertion): static`
- [ ] When Closure: execute and check truthiness
- [ ] When string: defer to AI verification (placeholder for now, skip in `--no-ai` mode)
- [ ] Add `Step::test(string ...$tests): static`
- [ ] Add tests for closure verification, test gates

---

## Priority 7: AI Integration

### 7.1 Instruct — AI-Directed Code Generation

**Why:** `instruct()` delegates contextual, creative tasks to an AI model. It uses the Action system's AI affordances (tool schemas, `fromAI()` hydration) to let the AI call structured tools rather than generating raw code.

```php
$step->instruct('Create a dashboard with team memberships', fn (Instruct $i) => $i
    ->creating('app/Livewire/Dashboard.php')
    ->using('app/Models/User.php', 'app/Models/Team.php')
    ->testing('tests/Feature/DashboardTest.php')
    ->review()
);
```

- [ ] Create `src/Builders/InstructBuilder.php` with `creating()`, `modifying()`, `using()`, `with()`, `rules()`, `like()`, `testing()`, `reference()`, `review()`, `bake()`
- [ ] Create `src/Actions/AI/InstructAction.php`
- [ ] Create `src/Payloads/InstructPayload.php`
- [ ] Create `src/AI/AIManager.php` — provider registry, model resolution
- [ ] Create `src/AI/Contracts/AIProvider.php` interface with `complete()` and `completeWithTools()`
- [ ] Create `src/AI/Providers/AnthropicProvider.php`
- [ ] Create `src/AI/Providers/OpenAIProvider.php`
- [ ] Create `src/AI/Providers/OllamaProvider.php`
- [ ] Create `src/AI/AIConversation.php` — manages the tool-use loop
- [ ] Create `src/AI/Prompts/InstructPromptBuilder.php`
- [ ] Add AI affordance methods to Action interface: `toolName()`, `toolDescription()`, `schema()`, `fromAI()`
- [ ] Implement `schema()` and `fromAI()` on all existing actions (ModifyAction, FileAction, ConfigAction, EnvAction, ArtisanAction, etc.)
- [ ] The instruct executor sends the AI tool definitions built from action schemas
- [ ] AI returns tool calls → executor hydrates actions via `fromAI()` → executes through the same pipeline
- [ ] Add `Step::instruct(string $description, ?Closure $callback = null): static`
- [ ] Add `--no-ai` CLI flag: skip instruct actions entirely
- [ ] Add `--review` CLI flag: pause before every AI-directed step for approval
- [ ] Add `->bake()` support: cache AI output as a diff, replay deterministically on future runs
- [ ] Create `src/AI/Fakes/FakeAIProvider.php` for testing
- [ ] Add tests with fake AI provider

### 7.2 Smart Commit Messages

**Why:** When `autoCommit(smart: true)` is set, the commit message should be AI-generated from the diff rather than using the step name.

- [ ] Create `src/AI/Prompts/CommitMessagePromptBuilder.php`
- [ ] Create `src/Execution/AICommitMessageGenerator.php` implementing `CommitMessageGenerator`
- [ ] The generator calls `git diff --staged` to get the diff, sends it to the AI
- [ ] Fall back to `DefaultCommitMessageGenerator` if AI is unavailable or `--no-ai` is set
- [ ] Wire up in Runner: use `AICommitMessageGenerator` when `smartCommit` is true
- [ ] Add tests with fake AI provider

### 7.3 AI Error Recovery

**Why:** When a step fails, Compose can use AI to diagnose the error and suggest a fix, optionally retrying automatically.

- [ ] Create `src/AI/Recovery/RecoveryAgent.php`
- [ ] Create `src/AI/Recovery/RecoveryBudget.php` — tracks retry attempts per step (default: 3)
- [ ] Create `src/AI/Recovery/RecoveryContext.php` — bundles error output, step context, project state
- [ ] Create `src/AI/Prompts/RecoveryPromptBuilder.php`
- [ ] Integrate into `ErrorRecovery` pipeline middleware (or into `ExecuteActions` pipe)
- [ ] When an action fails and AI is available: diagnose, suggest fix, optionally retry
- [ ] Add `--no-recovery` CLI flag: fail immediately on any error
- [ ] Add CLI output for recovery: `🔧 Diagnosing...`, fix description, retry status
- [ ] Add tests with fake AI provider

---

## Priority 8: External AI Surfaces

### 8.1 MCP Server

**Why:** Expose Compose's tools as an MCP server so external AI clients (Claude Code, etc.) can use them natively. The MCP server reads tool definitions from the same action schemas the internal instruct executor uses.

- [ ] Create `src/MCP/ComposeMCPServer.php`
- [ ] Create `src/MCP/MCPTransport.php` — stdio and HTTP modes
- [ ] Add `compose mcp` CLI command
- [ ] Add `compose mcp --port=3000` for HTTP mode
- [ ] All actions with `toolName()`/`schema()`/`fromAI()` are automatically exposed
- [ ] Introspection actions (InspectAction, ReadFileAction, ListFilesAction, SearchAction) are exposed as read-only tools
- [ ] Add tests for tool listing and tool execution via MCP

### 8.2 Skill File Generator

**Why:** Generate a markdown document that teaches external AI tools (Claude Code) about the project's Compose capabilities. This is a static export, not a live server.

- [ ] Create `src/Skill/SkillGenerator.php`
- [ ] Add `compose skill` CLI command — outputs to stdout
- [ ] Add `compose skill --output=CLAUDE.md` — writes to file
- [ ] Include: available CLI commands, project structure, current recipe steps, conventions
- [ ] Generate from action metadata (same source as MCP and instruct)
- [ ] Add tests for generator output

---

## Priority 9: CLI Enhancements

### 9.1 Additional CLI Commands

- [ ] `compose plan [recipe]` — dry-run, show what would happen (exists, needs polish)
- [ ] `compose steps [recipe]` — list steps without action detail
- [ ] `compose inspect [--step=<name>]` — detailed view of a single step
- [ ] `compose run --from=<step>` — resume from a specific step (by number or name)
- [ ] `compose run --step=<step>` — run only a specific step
- [ ] `compose run --no-commit` — skip all git commits
- [ ] `compose run --verbose` — show full command output
- [ ] `compose run --quiet` — only show errors

### 9.2 CLI Output Polish

- [ ] Add ✨ marker for AI-directed steps in output
- [ ] Add spinner/progress indicator for long-running actions
- [ ] Add timing information per step
- [ ] Color-code: green for success, red for failure, yellow for warnings, gray for auto-commit
- [ ] Show summary at the end: steps completed, time elapsed, files changed

### 9.3 Default Recipe Filename

**Why:** The `ComposeCommand` defaults to `recipe.php` but the docs show `compose.php` as the convention. Support both with a fallback chain.

- [ ] Change default to look for `compose.php` first, then `recipe.php`
- [ ] If neither exists, show a helpful error message
- [ ] Update documentation to recommend `compose.php` as the primary filename

---

## Dependency Notes

| Feature | Depends On |
|---------|-----------|
| Laravel helpers (3.2) | Artisan actions (3.1) |
| Config manipulation (3.5) | Nette integration (4.1) or standalone parser |
| Modify builder (4.1) | Nette PHP Generator (already in deps) |
| Recipe system (5.1) | None — can be built independently |
| Instruct (7.1) | Modify (4.1), AI providers, action AI affordances |
| Smart commits (7.2) | AI providers |
| Error recovery (7.3) | AI providers |
| MCP server (8.1) | Action AI affordances from 7.1 |
| Skill generator (8.2) | Action metadata from 7.1 |
| `--from` resume (9.1) | Rollback scope refactor (2.4) |