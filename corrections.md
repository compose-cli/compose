Fix Corrections Issues

All 9 issues from corrections.md have been verified as still present. Below is the fix plan for each, in order.



2. Step::operations() Lazy Resolution

File: src/Step.php

The operations() method (line ~326) returns $this->operations without checking $this->resolved. Fix: add a lazy resolution guard.

public function operations(): array
{
    if (! $this->resolved) {
        $this->resolveOperations();
    }

    return $this->operations;
}

No test changes needed — this is a defensive fix that makes existing behavior more robust.



3. Remove Dead countOperations()

File: src/Actions/Env/EnvAction.php

The private countOperations() method (line ~150) counts every operation as 1 regardless of type ($op['type'] === 'when' ? 1 : 1). Replace its two call sites with count($this->operations) and delete the method.



4 + 8. Convert Sink to Direct Execution (also fixes force-skip bug)

Files: src/Actions/Sink.php, tests/Unit/SinkActionTest.php

Convert Sink from a command-based (curl) action to a direct-execution action:





Remove command() override



Implement execute() using file_get_contents / file_put_contents



Add force: false skip guard (!$this->force && file_exists($target) early return)



Convert rollback from rollback() / canBeRolledBack() to rollbackDirect() / canRollbackDirect()



Add private descriptionArray() helper



Update tests to use InteractsWithFilesystem trait and test actual file I/O instead of toGenerateCommand()



5. Clean Up Artisan Builder Entry Types (keep interleaved ordering)

File: src/Builders/Artisan.php

The $entries array is list<string|Action> which mixes raw strings with ConfigAction objects. The user wants to keep interleaved ordering. Fix approach:





Introduce a lightweight tagged union — e.g., store all entries as array{type: 'command', value: string} | array{type: 'action', value: Action} — so the array has a uniform structure



Update run() to push ['type' => 'command', 'value' => $command]



Update config() to push ['type' => 'action', 'value' => new ConfigAction(...)]



Update actions() to map over the unified array:

public function actions(): array
{
    return array_map(
        fn (array $entry): Action => $entry['type'] === 'command'
            ? new ArtisanAction($entry['value'])
            : $entry['value'],
        $this->entries,
    );
}

This preserves interleaved ordering while making the data structure self-documenting.



6. Extract Arg Separator to PendingCommand

Files: src/Actions/PendingCommand.php, src/Actions/Composer/ComposerRun.php, src/Actions/Node/NodeRun.php





Add withArgs(array $args, ?string $separator = '--'): static to PendingCommand



Simplify ComposerRun::command() to use ->withArgs((array) $this->args)



Simplify NodeRun::command() to use ->withArgs((array) $this->args, $usesSeparator ? '--' : null)



Existing tests for ComposerRun and NodeRun should continue to pass since behavior is unchanged



7. Harden Dangerous Path Guard

CRITICAL WARNING: See fix-7-critical-bug.md for a detailed post-mortem. A previous attempt at this fix caused Filesystem::deleteDirectory() to delete the project's parent directory because normalizePath() was only applied as a fallback when realpath() returned false. On Windows, realpath() returns backslash paths, so the forward-slash parent check silently failed.

File: src/Execution/Runner.php

The guardAgainstDangerousPath() method (line ~173) is missing:





Parent-of-cwd check (deleting an ancestor directory is dangerous)



Consistent path normalization — normalizePath() must wrap all path values including successful realpath() results, not just the fallback

Fix: Add a normalizePath() helper that does rtrim(str_replace('\\', '/', $path), '/'). Apply it unconditionally to every resolved path:

$resolved = $this->normalizePath(realpath($path) ?: $path);
$cwd = $this->normalizePath(realpath((string) getcwd()) ?: (string) getcwd());

NOT this (broken on Windows):

$resolved = realpath($path) ?: $this->normalizePath($path);  // WRONG

Add parent-of-cwd check via str_starts_with($cwd, $resolved . '/').

Add tests for:





Path that is a parent of cwd



Empty string path



9. Document GitBranch ProcessExecutor Bypass

File: src/Actions/Git/GitBranch.php

Per the corrections doc recommendation (Option A): add a documentation comment explaining that GitBranch creates Process instances directly and bypasses ProcessExecutor::fake(). Tests for this action should use actual git repos in temp directories. Verify existing tests already do this or update them if needed.



10. ModifyBuilder Mixed-Operation Validation

File: src/Builders/ModifyBuilder.php

Add validation in operations() that throws \LogicException when both PHP class operations and JSON operations are present in the same builder. Detect PHP ops by their type field (add_trait, remove_trait, add_interface, etc.) and JSON ops by the json_ prefix.



Execution Order

Items 2, 3, 9, and 10 are independent leaf changes. Items 4+8 are coupled. Item 6 is self-contained. Items 5 and 7 are independent. All can be done in the listed order. Run composer check after all changes.

Fix #7 must be applied with extreme care. The guardAgainstDangerousPath method gates Filesystem::deleteDirectory() — if the guard fails to throw, real directories get deleted. Run the fresh guard tests in isolation first (vendor/bin/pest --filter="Runner fresh guard") and verify they pass before running the full suite. See fix-7-critical-bug.md for the full post-mortem.

Status (at time of repo deletion)

Fixes 2, 3, 4+8, 5, 6, 9, and 10 were applied and all their tests were passing. Fix 7 was applied with the normalization bug described above, which caused the test suite to delete src/ and other directories. All fixes need to be re-applied from a fresh clone.


# Critical Bug: Fix #7 guardAgainstDangerousPath — Windows Path Normalization

## What Happened

While applying fix #7 (harden `guardAgainstDangerousPath()`), a new parent-of-cwd check was added that used forward-slash comparison:

```php
if (str_starts_with($cwd, $resolved . '/')) {
```

On Windows, `realpath()` returns paths with **backslashes** (e.g. `C:\Users\Wyatt\Desktop\Projects`). The normalization helper (`normalizePath()`) was only applied as a **fallback** when `realpath()` returned false — meaning existing, resolvable paths kept their Windows backslashes.

This caused the parent-of-cwd check to silently fail: `str_starts_with('C:\...\compose', 'C:\...\Projects/')` is always false on Windows because the separator doesn't match.

Because the guard didn't throw, the test `'throws when fresh mode targets a parent of the current working directory'` proceeded past the guard and `Filesystem::deleteDirectory()` was called on the parent of cwd, deleting the `src/`, `config/`, and other directories in the project.

## Root Cause

`normalizePath()` must be applied **unconditionally** to both `realpath()` results and fallback values — not just when `realpath()` returns false.

## The Correct Fix

In `src/Execution/Runner.php`, the `guardAgainstDangerousPath()` method should **always** normalize paths through the helper, even when `realpath()` succeeds:

```php
private function guardAgainstDangerousPath(?string $path): void
{
    if ($path === null || $path === '') {
        throw new DangerousPathException(
            'Cannot use fresh mode: no working directory specified.',
        );
    }

    $resolved = $this->normalizePath(realpath($path) ?: $path);
    $cwd = $this->normalizePath(realpath((string) getcwd()) ?: (string) getcwd());

    if ($resolved === $cwd || $resolved === '.') {
        throw new DangerousPathException(
            "Cannot use fresh mode: the path '{$path}' resolves to the current working directory.",
        );
    }

    if (str_starts_with($cwd, $resolved . '/')) {
        throw new DangerousPathException(
            "Cannot use fresh mode: the path '{$path}' is a parent of the current working directory.",
        );
    }

    $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;

    if ($home !== null) {
        $resolvedHome = $this->normalizePath(realpath($home) ?: $home);
        if ($resolved === $resolvedHome) {
            throw new DangerousPathException(
                "Cannot use fresh mode: the path '{$path}' resolves to the home directory.",
            );
        }
    }

    $isRoot = $resolved === '/'
        || $resolved === ''
        || preg_match('/^[A-Z]:[\\/]?$/i', $resolved);

    if ($isRoot) {
        throw new DangerousPathException(
            "Cannot use fresh mode: the path '{$path}' resolves to a filesystem root.",
        );
    }
}

private function normalizePath(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/');
}
```

The key difference from the broken version:

```diff
- $resolved = realpath($path) ?: $this->normalizePath($path);
- $cwd = realpath((string) getcwd()) ?: $this->normalizePath((string) getcwd());
+ $resolved = $this->normalizePath(realpath($path) ?: $path);
+ $cwd = $this->normalizePath(realpath((string) getcwd()) ?: (string) getcwd());
```

And likewise for the home directory:

```diff
- $resolvedHome = realpath($home) ?: $this->normalizePath($home);
+ $resolvedHome = $this->normalizePath(realpath($home) ?: $home);
```

## Test for This

The test added to `tests/Unit/RunnerTest.php` inside `describe('Runner fresh guard', ...)` is still correct — it just needs the fix above to not be destructive:

```php
it('throws when fresh mode targets a parent of the current working directory', function (): void {
    ProcessExecutor::fake();

    $parent = dirname((string) getcwd());

    $recipe = compose('Test Recipe')
        ->in($parent, fresh: true);

    $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

    $recipe->run();
})->throws(DangerousPathException::class);
```

## Lesson

Any path comparison logic that involves separators (prefix checks, suffix checks, contains checks) must normalize **all** paths to a consistent separator **before** comparison — even when `realpath()` succeeds. `realpath()` returns OS-native separators, which differ between Windows (`\`) and Unix (`/`).