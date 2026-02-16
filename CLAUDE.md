# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Compose is an intelligent scaffolding tool for PHP projects. It provides a recipe-based automation framework where developers define multi-step project setup workflows (composer installs, node packages, git operations) with automatic rollback on failure.

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

## Architecture

**Execution flow:** User defines a recipe via `compose()` helper → adds steps with `->step()` → calls `->compose()` to execute (or `->plan()` for dry-run) → `Runner` processes each step through a `Pipeline` → `ResolveOperations` pipe calls the step callback to build actions → `ExecuteActions` pipe runs each action via `ProcessExecutor` → on failure, `RollbackManager` rolls back completed actions in LIFO order.

**Key layers:**

- **Recipe definition** (`Compose`, `Step`): Fluent API for configuring scaffolding recipes with steps containing composer/node/git operations.
- **Actions** (`src/Actions/`): Command builders for Composer (install/remove/run), Node (install/remove/run), and Git (clone). Each extends `Action` base class, builds a `PendingCommand`, and optionally defines rollback behavior.
- **Execution** (`src/Execution/`): `Runner` orchestrates recipe execution. `Pipeline` passes `StepContext` through pipes (`ResolveOperations`, `ExecuteActions`). `ProcessExecutor` wraps Symfony Process. `RollbackManager` handles failure recovery.
- **Events** (`src/Events/`): `EventDispatcher` fires lifecycle events (StepStarting/Completed/Failed, ActionExecuting/Completed/Failed, RollbackStarting/Completed).
- **Contracts**: `Operation` interface for operation enums (`PackageOperation`, `GitOperation`), `AI` interface for AI provider enums.

**Results hierarchy:** `ActionResult` → `StepResult` (contains action results + rollback info) → `RunResult` (contains all step results). `Plan`/`StepPlan` for dry-run previews.

## Testing

- Uses Pest 4.0 with `InteractsWithFilesystem` trait for temp directory management (auto creates/cleans temp dirs).
- `ProcessExecutor::fake()` enables testing without running real commands; supports pattern-matching responses.
- Custom expectations: `toGenerateCommand()` to verify action command output, `toBeOperation()` to verify action types.
- Helper `context()` function in Pest.php creates a `RecipeContext` for tests.

## Code Style

- PHP 8.3+ required, Rector targets PHP 8.4 syntax (property hooks, etc.).
- Laravel Pint for formatting (PSR-12 based), auto-enforced via GitHub Actions.
- Strict typing throughout; uses enums, readonly properties, and PHP 8.4 features.
