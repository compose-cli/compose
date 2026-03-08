# Compose — Corrections Plan

This document contains concrete fixes for bugs, inconsistencies, and correctness issues in the Compose codebase. Each item is scoped, actionable, and includes the rationale and affected files. Work through them in order — some later items depend on earlier ones.

---

## 2. `Step::operations()` Silently Returns Empty Before Resolution

**Problem:** If `operations()` is called before `resolveOperations()`, it returns an empty array with no warning. This is a latent bug — any code path that forgets to resolve first gets silent no-ops.

**Affected file:** `src/Step.php`

**Fix:** Make `operations()` call `resolveOperations()` lazily:

```php
/**
 * @return Action[]
 */
public function operations(): array
{
    if (! $this->resolved) {
        $this->resolveOperations();
    }

    return $this->operations;
}
```

This makes the resolution idempotent and eliminates the ordering dependency. The `ResolveOperations` pipe becomes a no-op rather than a critical ordering requirement, which is safer.

---

## 3. `EnvAction::countOperations()` Is a No-Op

**Problem:** The private method `countOperations()` counts every operation as 1 regardless of type. The method body is identical to just calling `count($operations)`. It looks like it was intended to handle `when` blocks differently (e.g., counting inner operations) but was never completed.

**Affected file:** `src/Actions/Env/EnvAction.php`

**Fix:** Remove the method entirely and replace its two call sites with `count($this->operations)`:

```php
// In execute():
$opCount = count($this->operations);

// In describe():
$opCount = count($this->operations);
```

Delete the `countOperations()` method.

---

## 4. `Sink` Action Should Use PHP-Native HTTP Instead of Shelling to `curl`

**Problem:** `Sink` builds a `curl` command via `PendingCommand`. This means:
- It won't work on Windows without curl installed
- It can't be tested without `ProcessExecutor::fake()`
- It's inconsistent with other file operations that use direct execution

**Affected file:** `src/Actions/Sink.php`

**Fix:** Convert `Sink` to a direct-execution action. Remove the `command()` override and implement `execute()` instead:

```php
#[\Override]
public function execute(RecipeContext $context): ActionResult
{
    $url = $this->resolveUrl();
    $target = $this->resolvePath($this->resolveTarget());

    $directory = dirname($target);
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        return ActionResult::failure(
            errorOutput: "Failed to create directory: {$directory}",
            command: $this->descriptionArray(),
        );
    }

    if (!$this->force && file_exists($target)) {
        return ActionResult::success(
            command: $this->descriptionArray(),
            output: "Skipped (exists): {$this->resolveTarget()}",
        );
    }

    $contents = @file_get_contents($url);

    if ($contents === false) {
        return ActionResult::failure(
            errorOutput: "Failed to fetch: {$url}",
            command: $this->descriptionArray(),
        );
    }

    if (file_put_contents($target, $contents) === false) {
        return ActionResult::failure(
            errorOutput: "Failed to write: {$this->resolveTarget()}",
            command: $this->descriptionArray(),
        );
    }

    return ActionResult::success(
        command: $this->descriptionArray(),
        output: "Fetched: {$this->resolveTarget()} (" . strlen($contents) . " bytes)",
    );
}
```

Also convert the rollback to `rollbackDirect()`:

```php
#[\Override]
public function canRollbackDirect(): bool
{
    return $this->force;
}

#[\Override]
public function rollbackDirect(RecipeContext $context): ActionResult
{
    $target = $this->resolvePath($this->resolveTarget());

    if (file_exists($target)) {
        unlink($target);
    }

    return ActionResult::success(
        command: ['rollback:sink', $this->resolveTarget()],
        output: "Deleted: {$this->resolveTarget()}",
    );
}
```

Remove the old `command()`, `rollback()`, and `canBeRolledBack()` methods. Add a private `descriptionArray()` method returning `['sink', $this->resolveTarget()]`.

**Update the test** (`tests/Unit/SinkActionTest.php`) — it likely uses `toGenerateCommand()` expectations. Convert those tests to use the filesystem trait and test actual file creation/rollback instead.

---

## 5. `Artisan` Builder Mixes Strings and Action Objects in `$entries`

**Problem:** The `Artisan` builder's `config()` method pushes `ConfigAction` instances directly into `$entries`, while all other methods push strings. The `actions()` method then checks `is_string($entry)` to decide whether to wrap in `ArtisanAction`. This inconsistency means:
- Config actions bypass any future transformation logic added to `actions()`
- The `$entries` type is `list<string|Action>` which is awkward
- The builder is leaking action construction responsibility

**Affected file:** `src/Builders/Artisan.php`

**Fix:** Store config operations separately and merge them in `actions()`:

```php
/** @var list<string> */
protected array $commands = [];

/** @var list<Action> */
protected array $configActions = [];
```

Change `run()` and all methods that call it to push to `$this->commands`. Change `config()` to push to `$this->configActions`. Then:

```php
/**
 * @return list<Action>
 */
public function actions(): array
{
    $actions = array_map(
        fn (string $command): ArtisanAction => new ArtisanAction($command),
        $this->commands,
    );

    return array_merge($actions, $this->configActions);
}
```

**Note:** This changes the ordering — config actions will now come after all artisan commands rather than being interleaved at their declaration position. If ordering matters (e.g., a config change must happen between two artisan commands), a more involved approach is needed: use a tagged union array like `['type' => 'command', 'value' => '...']` and `['type' => 'action', 'value' => $configAction]`. Decide which behavior is correct and document it.

^ **I want to keep the interleaved at declaration position behavior**

---

## 6. Duplicated Argument Separator Logic in `ComposerRun` and `NodeRun`

**Problem:** Both `ComposerRun` and `NodeRun` manually handle `--` argument separators. `NodeRun` additionally checks the package manager to decide whether to use a separator. This logic is duplicated and should live in `PendingCommand`.

**Affected files:**
- `src/Actions/PendingCommand.php`
- `src/Actions/Composer/ComposerRun.php`
- `src/Actions/Node/NodeRun.php`

**Fix:** Add a `withArgs()` method to `PendingCommand` that accepts args and an optional separator:

```php
/**
 * Add arguments after an optional separator (e.g. '--').
 *
 * @param string[] $args
 */
public function withArgs(array $args, ?string $separator = '--'): static
{
    if ($args === []) {
        return $this;
    }

    if ($separator !== null) {
        $this->arguments[] = $separator;
    }

    array_push($this->arguments, ...$args);

    return $this;
}
```

Then simplify `ComposerRun::command()`:

```php
public function command(): PendingCommand
{
    return $this->composer('run', $this->script)
        ->withArgs((array) $this->args);
}
```

And `NodeRun::command()`:

```php
public function command(): PendingCommand
{
    $usesRun = match ($this->manager()) {
        Node::Yarn, Node::Bun => false,
        default => true,
    };

    $usesSeparator = match ($this->manager()) {
        Node::Yarn, Node::Bun => false,
        default => true,
    };

    $cmd = $usesRun
        ? $this->node('run', $this->script)
        : $this->node($this->script);

    return $cmd->withArgs((array) $this->args, $usesSeparator ? '--' : null);
}
```

---

## 7. `DangerousPathException` Guard Doesn't Handle Symlinks or Edge Cases

**Problem:** In `Runner::guardAgainstDangerousPath()`, `realpath()` returns `false` for non-existent paths, and the fallback to the raw path means comparisons against `realpath(getcwd())` can fail due to normalization differences. Symlinks are also not resolved consistently.

**Affected file:** `src/Execution/Runner.php`

**Fix:** Normalize paths consistently before comparison:

```php
private function guardAgainstDangerousPath(?string $path): void
{
    if ($path === null || $path === '') {
        throw new DangerousPathException(
            'Cannot use fresh mode: no working directory specified.',
        );
    }

    // Normalize: resolve what exists, otherwise normalize separators
    $resolved = realpath($path) ?: rtrim(str_replace('\\', '/', $path), '/');
    $cwd = realpath((string) getcwd()) ?: rtrim(str_replace('\\', '/', (string) getcwd()), '/');

    if ($resolved === $cwd || $resolved === '.') {
        throw new DangerousPathException(
            "Cannot use fresh mode: the path '{$path}' resolves to the current working directory.",
        );
    }

    // Also check if the target is a parent of cwd (deleting a parent is dangerous)
    if (str_starts_with($cwd, $resolved . '/')) {
        throw new DangerousPathException(
            "Cannot use fresh mode: the path '{$path}' is a parent of the current working directory.",
        );
    }

    $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;

    if ($home !== null) {
        $resolvedHome = realpath($home) ?: rtrim(str_replace('\\', '/', $home), '/');
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
```

**Add tests** for the new parent-directory check and the empty-string edge case.

---

## 8. `Sink` Doesn't Guard Against `force: false` Skip Correctly

**Problem:** In the current (curl-based) implementation, there's no check for `force: false` before executing the curl command. The rollback method returns null when `!$this->force`, but the download still happens and overwrites the file.

**Affected file:** `src/Actions/Sink.php`

**Fix:** If you do correction #4 (convert to direct execution), this is handled in that implementation with the early return for `!$this->force && file_exists($target)`. If you keep the curl-based approach for any reason, add a `execute()` override that checks for the skip condition before falling through to command execution:

```php
#[\Override]
public function execute(RecipeContext $context): ?ActionResult
{
    if (!$this->force) {
        $target = $this->resolvePath($this->resolveTarget());
        if (file_exists($target)) {
            return ActionResult::success(
                command: ['sink', $this->resolveTarget()],
                output: "Skipped (exists): {$this->resolveTarget()}",
            );
        }
    }

    return null; // fall through to command-based execution
}
```

---

## 9. `GitBranch` Creates Process Instances Directly Instead of Using `ProcessExecutor`

**Problem:** `GitBranch::execute()` and `rollbackDirect()` create `Symfony\Component\Process\Process` instances directly, bypassing `ProcessExecutor`. This means these operations aren't captured by `ProcessExecutor::fake()` during testing — they'll actually try to run git commands even when faked.

**Affected file:** `src/Actions/Git/GitBranch.php`

**Fix:** This is a design tension — direct-execution actions receive `RecipeContext` but not `ProcessExecutor`. Two options:

**Option A (minimal):** Accept the inconsistency but document it. Add a note to `GitBranch` that it bypasses the fake. Ensure tests for `GitBranch` use the filesystem trait and actual git repos in temp directories rather than relying on `ProcessExecutor::fake()`.

**Option B (cleaner):** Refactor `GitBranch` to be a command-based action. The original branch detection can move to a preflight-like mechanism, or the runner can handle it. This is more invasive but makes testing consistent.

Recommend **Option A** for now with a `// Note:` comment, and revisit if the inconsistency causes real test pain.

---

## 10. `ModifyBuilder` Allows Invalid Operation Combinations Without Validation

**Problem:** A user can call PHP class operations (`addTrait`) and JSON operations (`json()`) on the same `ModifyBuilder`. This will always fail at execution time in `ModifyAction` because PHP ops require `.php` files and JSON ops require `.json` files. The error comes late and is confusing.

**Affected file:** `src/Builders/ModifyBuilder.php`

**Fix:** Add validation in `operations()` that checks for conflicting operation categories:

```php
public function operations(): array
{
    $hasPhp = false;
    $hasJson = false;

    foreach ($this->operations as $op) {
        if (in_array($op->type, [
            'add_trait', 'remove_trait', 'add_interface', 'add_import', 'remove_import',
            'add_method', 'add_property', 'add_constant', 'add_to_array', 'add_to_method',
            'remove_method',
        ])) {
            $hasPhp = true;
        }

        if (str_starts_with($op->type, 'json_')) {
            $hasJson = true;
        }
    }

    if ($hasPhp && $hasJson) {
        throw new \LogicException(
            'ModifyBuilder cannot mix PHP class operations with JSON operations. '
            . 'Use separate modify() calls for different file types.'
        );
    }

    return $this->operations;
}
```

---

## Summary Checklist

| # | Fix | Severity | Files |
|---|-----|----------|-------|
| 2 | `Step::operations()` lazy resolution | Defensive | `Step.php` |
| 3 | Remove dead `countOperations()` | Cleanup | `EnvAction.php` |
| 4 | Convert `Sink` to direct execution | Improvement | `Sink.php`, tests |
| 5 | Split `Artisan` builder entry types | Consistency | `Artisan.php` |
| 6 | Extract arg separator to `PendingCommand` | DRY | `PendingCommand`, `ComposerRun`, `NodeRun` |
| 7 | Harden dangerous path guard | Defensive | `Runner.php` |
| 8 | `Sink` force-skip bug | **Bug** | `Sink.php` |
| 9 | `GitBranch` bypasses fake executor | Test gap | `GitBranch.php` |
| 10 | `ModifyBuilder` mixed-operation validation | Defensive | `ModifyBuilder.php` |
