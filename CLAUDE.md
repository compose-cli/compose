# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Compose is an intelligent scaffolding tool for PHP projects. It provides a recipe-based automation framework where developers define multi-step project setup workflows (composer installs, node packages, git operations, file operations, artisan commands) with automatic rollback on failure.

## Commands

```bash
composer test              # Run tests (Pest)
composer format            # Format code (Laravel Pint)
composer rector            # Run Rector refactoring
composer rector:dry        # Rector dry-run
composer check             # Run all checks (rector + format + test)
composer compose           # Execute a recipe (php compose.php)
```

Run a single test file:
```bash
vendor/bin/pest tests/Unit/RunnerTest.php
```

Run a single test by name:
```bash
vendor/bin/pest --filter="test name here"
```

**Rector on Windows:** Parallel mode times out. Use `--debug` flag to run single-threaded:
```bash
php vendor/bin/rector process --debug
php vendor/bin/rector process --dry-run --debug
```

## Architecture

**Execution flow:** User defines a recipe via `compose()` helper → adds steps with `->step()` → calls `->compose()` to execute (or `->plan()` for dry-run) → `Runner` processes each step through a `Pipeline` → `ResolveOperations` pipe calls the step callback to build actions → `ExecuteActions` pipe runs each action via `ProcessExecutor` (or directly for file actions) → on failure, `RollbackManager` rolls back completed actions in LIFO order.

**Key layers:**

- **Recipe definition** (`Compose`, `Step`, `Recipe`): Fluent API for configuring scaffolding recipes with steps containing composer/node/git/artisan/file operations. `Recipe` is an abstract base class for reusable, class-based step definitions with dependency resolution via `requires()`.
- **Actions** (`src/Actions/`): Two kinds of actions exist:
  - **Command-based actions** (Composer, Node, Git, Artisan, Sink): Build a `PendingCommand` via `command()` and execute through `ProcessExecutor` (Symfony Process). Rollback also via shell commands.
  - **Direct actions** (File/*): Override `execute()` to perform PHP-native I/O. Return `ActionResult` directly. Rollback via `rollbackDirect()`. Identified by `isDirect()` (when `command()` returns null).
- GitCommit is a direct action that atomically stages and commits, capturing the parent SHA for `git reset --mixed` rollback. Auto-commits are pushed onto the RollbackManager stack.
- **Builders** (`src/Builders/`): `Artisan` fluent builder collects artisan commands and compiles them to `ArtisanAction[]`.
- **Execution** (`src/Execution/`): `Runner` orchestrates recipe execution. `Pipeline` passes `StepContext` through pipes (`ResolveOperations`, `ExecuteActions`). `ProcessExecutor` wraps Symfony Process. `RollbackManager` handles failure recovery.
- **Events** (`src/Events/`): `EventDispatcher` fires lifecycle events (StepStarting/Completed/Failed, ActionExecuting/Completed/Failed, RollbackStarting/Completed).
- **Contracts**: `Operation` interface for operation enums (`PackageOperation`, `GitOperation`, `FileOperation`, `ArtisanOperation`), `AI` interface for AI provider enums, `CommitMessageGenerator` for auto-commit messages.

**Results hierarchy:** `ActionResult` → `StepResult` (contains action results + rollback info) → `RunResult` (contains all step results). `Plan`/`StepPlan` for dry-run previews.

## Action Hierarchy

```
Action (abstract)
├── ComposerAction (abstract) → ComposerInstall, ComposerRemove
├── ComposerRun
├── NodeAction (abstract) → NodeInstall, NodeRemove
├── NodeRun
├── GitClone, GitInit, GitAdd, GitCommit
├── ArtisanAction
├── Sink
└── File actions (direct): CreateFile, ReadFile, CopyFile, AppendFile, DeleteFile
```

**Adding a new action:** Extend `Action`, implement `type()` (returns an `Operation` enum case), and either `command()` (for shell-based) or `execute()` (for direct). Add `#[\Override]` to all overridden methods. Optionally implement `rollback()` / `rollbackDirect()` and `canBeRolledBack()` / `canRollbackDirect()`.

## Testing

- Uses Pest 4.0 with `InteractsWithFilesystem` trait for temp directory management (auto creates/cleans temp dirs).
- `ProcessExecutor::fake()` enables testing without running real commands; supports pattern-matching responses. Call `ProcessExecutor::reset()` in `afterEach` when using fakes.
- Custom expectations: `toGenerateCommand()` to verify action command output, `toBeOperation()` to verify action types.
- Helper `context()` function in Pest.php creates a `RecipeContext` with optional overrides for `phpBinary`, `composerBinary`, `gitBinary`, `nodeManager`, `workingDirectory`.
- **File action tests** use `$this->tempPath` and `$this->createFile()` from `InteractsWithFilesystem`, with `context(workingDirectory: $this->tempPath)`.
- **Command-based action tests** use `->withContext(context(...))` and assert with `toGenerateCommand()`.
- Test files use `describe()` blocks for grouping related tests (execution, describe, rollback).

## Code Style & Conventions

- PHP 8.3+ required, Rector targets PHP 8.4 syntax.
- `declare(strict_types=1)` on every PHP file (enforced by Rector's `DeclareStrictTypesRector`).
- `#[\Override]` on all overridden methods including abstract implementations (enforced by Rector's `AddOverrideAttributeToOverriddenMethodsRector`).
- Laravel Pint for formatting (PSR-12 based), auto-enforced via GitHub Actions.
- Constructor property promotion preferred everywhere. Exception: variadic params (e.g. `DeleteFile`) can't be promoted and use manual assignment.
- Readonly properties on DTOs and action constructor params. Mutable state only where needed (e.g. `Action::$allowFailure`, rollback tracking in file actions).
- Backed enums for operation types implementing `Operation` interface.
- Property hooks (PHP 8.4) for computed properties on result DTOs (`StepResult::$hasWarnings`, `RunResult::$stepsCompleted`, `RecipeConfig::$hasBase`).
- Fluent APIs return `static` for chaining (`Step`, `Compose`, `PendingCommand`, `Artisan` builder).
- Named arguments preferred in tests for clarity.

## Rector Rules

The rector.php config enforces these rules — run Rector before committing:
- `DeclareStrictTypesRector` — adds `declare(strict_types=1)` to all files
- `AddOverrideAttributeToOverriddenMethodsRector` — adds `#[\Override]` to overridden methods
- `PropertyHookRector` — converts to PHP 8.4 property hooks where applicable
- `RemoveUselessAssignFromPropertyPromotionRector`
- `ClassPropertyAssignToConstructorPromotionRector`
- PHP 8.4 set enabled via `withPhpSets(php84: true)`
