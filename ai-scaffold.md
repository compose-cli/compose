# Compose — AI Integration Scaffold

Shell out to AI CLI tools (Claude Code, aider, codex) for code generation tasks. The CLI tool handles conversation, tool use, and file manipulation. Compose handles prompt construction, execution, git-based patch caching, and rollback.

Follow all project conventions from CLAUDE.md.

---

## File Structure

```
src/
├── AI/
│   ├── CLIAgent.php
│   ├── InstructResult.php
│   ├── Bake/
│   │   └── PatchManager.php
│   └── Prompts/
│       ├── InstructPromptBuilder.php
│       └── CommitMessagePromptBuilder.php
├── Actions/
│   └── AI/
│       └── InstructAction.php
├── Builders/
│   └── InstructBuilder.php
├── Payloads/
│   └── InstructPayload.php
├── Enums/
│   └── AIAgent.php
│   └── InstructOperation.php
└── Execution/
    └── AICommitMessageGenerator.php
```

---

## 1. `src/Enums/AIAgent.php`

Each case knows its binary, how to pass a prompt, and its install instructions.

```php
<?php

declare(strict_types=1);

namespace Compose\Enums;

enum AIAgent: string
{
    case Claude = 'claude';
    case Aider = 'aider';
    case Codex = 'codex';

    /**
     * The binary name to execute.
     */
    public function binary(): string
    {
        return $this->value;
    }

    /**
     * Build the full command array for a code-editing prompt.
     *
     * Each tool has its own CLI conventions:
     *   claude: claude -p "prompt" --allowedTools "edit,write,bash" --output-format text
     *   aider:  aider --message "prompt" --yes-always --no-git
     *   codex:  codex -q "prompt" --approval-mode full-auto
     *
     * When $promptFile is set, the tool reads from the file instead of an
     * inline argument (avoids OS arg length limits on large prompts).
     *
     * @return list<string>
     */
    public function buildCommand(string $prompt, ?string $promptFile = null): array;

    /**
     * Build command for a one-shot prompt with no file editing.
     * Used for commit message generation.
     *
     * Flags should suppress file access and interactive features:
     *   claude: claude -p "prompt" --allowedTools "" --output-format text
     *   aider:  aider --message "prompt" --no-auto-commits --no-git
     *   codex:  codex -q "prompt" --approval-mode suggest
     *
     * @return list<string>
     */
    public function buildSimpleCommand(string $prompt): array;

    /**
     * Timeout for code-editing operations. AI is slow — be generous.
     */
    public function timeout(): float
    {
        return 600.0;
    }

    /**
     * Human-readable install instructions shown when the binary is missing.
     */
    public function installInstructions(): string
    {
        return match ($this) {
            self::Claude => 'Install Claude Code: npm install -g @anthropic-ai/claude-code' . "\n"
                          . 'Then run `claude` once to authenticate.',
            self::Aider  => 'Install aider: pip install aider-chat' . "\n"
                          . 'Set ANTHROPIC_API_KEY or OPENAI_API_KEY in your environment.',
            self::Codex  => 'Install Codex CLI: npm install -g @openai/codex' . "\n"
                          . 'Set OPENAI_API_KEY in your environment.',
        };
    }
}
```

---

## 2. `src/Enums/InstructOperation.php`

```php
<?php

declare(strict_types=1);

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum InstructOperation: string implements Operation
{
    case Instruct = 'instruct';
}
```

---

## 3. `src/AI/CLIAgent.php`

Thin `ProcessExecutor` wrapper with AI-specific concerns.

```php
<?php

declare(strict_types=1);

namespace Compose\AI;

use Compose\Enums\AIAgent;
use Compose\Execution\ActionResult;
use Compose\Execution\ProcessExecutor;
use Compose\RecipeContext;

class CLIAgent
{
    public function __construct(
        private readonly ProcessExecutor $executor,
        private readonly AIAgent $agent,
    ) {}

    /**
     * Execute a code-editing prompt.
     *
     * Long prompts (>4096 bytes) are written to a temp file and passed via
     * the tool's file-input flag to avoid OS arg length limits. The temp
     * file is cleaned up after execution regardless of outcome.
     */
    public function execute(string $prompt, RecipeContext $context): ActionResult
    {
        $promptFile = null;

        if (strlen($prompt) > 4096) {
            $promptFile = $this->writePromptFile($prompt, $context);
        }

        try {
            $command = $this->agent->buildCommand($prompt, $promptFile);

            return $this->executor->execute(
                $command,
                $context->workingDirectory,
                $this->agent->timeout(),
            );
        } finally {
            if ($promptFile !== null && file_exists($promptFile)) {
                unlink($promptFile);
            }
        }
    }

    /**
     * One-shot prompt with no file editing context.
     * Used for commit message generation.
     */
    public function prompt(string $prompt, RecipeContext $context): ActionResult
    {
        $command = $this->agent->buildSimpleCommand($prompt);

        return $this->executor->execute(
            $command,
            $context->workingDirectory,
            120.0,
        );
    }

    /**
     * Check if the CLI tool is installed and responsive.
     *
     * Runs `<binary> --version` with a short timeout. Returns false if
     * the binary is missing or hangs (e.g. waiting for auth).
     */
    public function isAvailable(): bool
    {
        $result = $this->executor->execute(
            [$this->agent->binary(), '--version'],
            null,
            10.0,
        );

        return $result->successful;
    }

    /**
     * Get the install instructions for the configured agent.
     */
    public function installInstructions(): string
    {
        return $this->agent->installInstructions();
    }

    private function writePromptFile(string $prompt, RecipeContext $context): string
    {
        $dir = $context->workingDirectory ?? sys_get_temp_dir();
        $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR
              . '.compose-prompt-' . bin2hex(random_bytes(8)) . '.md';

        file_put_contents($path, $prompt);

        return $path;
    }
}
```

---

## 4. `src/Builders/InstructBuilder.php`

`using()` is a **hint** to the AI tool — "this file is relevant." It does not read file contents into the prompt by default. Pass `include: true` to inline the contents. This lets recipe authors reference 20 files as context hints without bloating the prompt.

`like()` always inlines contents — if you're asking the AI to match a style, it needs to see the code.

No `review()` — deferred to a future version.

```php
<?php

declare(strict_types=1);

namespace Compose\Builders;

use Compose\Payloads\InstructPayload;

class InstructBuilder
{
    /** @var list<string> Files the AI should create */
    protected array $creating = [];

    /** @var list<string> Files the AI should modify */
    protected array $modifying = [];

    /** @var list<array{path: string, include: bool}> Context file hints */
    protected array $using = [];

    /** @var list<string> Style reference files (always inlined) */
    protected array $like = [];

    /** @var list<string> Freeform rules */
    protected array $rules = [];

    /** @var array<string, mixed> Key-value context */
    protected array $context = [];

    /** @var list<string> Test files to create or ensure pass */
    protected array $testing = [];

    protected bool $bake = false;

    /**
     * Hint that the AI should create this file.
     * Not a constraint — AI may create additional files beyond these.
     */
    public function creating(string ...$paths): static
    {
        array_push($this->creating, ...$paths);

        return $this;
    }

    /**
     * Hint that the AI should modify this file.
     */
    public function modifying(string ...$paths): static
    {
        array_push($this->modifying, ...$paths);

        return $this;
    }

    /**
     * Mark files as relevant context for the AI.
     *
     * By default this is a hint: the file path is listed in the prompt so the
     * AI tool knows to read it, but the contents are NOT inlined.
     *
     * Pass include: true to read the file contents into the prompt directly.
     * Use this sparingly — large files consume tokens.
     *
     *   ->using('app/Models/User.php')                    // hint only
     *   ->using('app/Models/Team.php', include: true)     // inlined
     */
    public function using(string $path, bool $include = false): static
    {
        $this->using[] = ['path' => $path, 'include' => $include];

        return $this;
    }

    /**
     * Use these files as style/pattern references.
     * Contents are always inlined — the AI needs to see the code to match it.
     */
    public function like(string ...$paths): static
    {
        array_push($this->like, ...$paths);

        return $this;
    }

    /**
     * Freeform rules the AI must follow.
     * Included verbatim in the prompt.
     *
     * @param list<string> $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = array_merge($this->rules, $rules);

        return $this;
    }

    /**
     * Key-value context pairs for the prompt.
     * Useful for project-specific info: framework version, PHP version, etc.
     *
     * @param array<string, mixed> $context
     */
    public function with(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Test files the AI should create or that should pass after the instruct.
     */
    public function testing(string ...$paths): static
    {
        array_push($this->testing, ...$paths);

        return $this;
    }

    /**
     * Enable git-based patch caching.
     * First run: AI executes, diff is saved as a patch file.
     * Subsequent runs: patch is applied directly, AI call is skipped.
     * If the instruct definition changes (different hash), AI re-runs.
     */
    public function bake(bool $bake = true): static
    {
        $this->bake = $bake;

        return $this;
    }

    /**
     * Compile to an immutable payload.
     */
    public function toPayload(string $description): InstructPayload
    {
        return new InstructPayload(
            description: $description,
            creating: $this->creating,
            modifying: $this->modifying,
            using: $this->using,
            like: $this->like,
            rules: $this->rules,
            context: $this->context,
            testing: $this->testing,
            bake: $this->bake,
        );
    }
}
```

---

## 5. `src/Payloads/InstructPayload.php`

```php
<?php

declare(strict_types=1);

namespace Compose\Payloads;

class InstructPayload
{
    public function __construct(
        public readonly string $description,
        /** @var list<string> */ public readonly array $creating,
        /** @var list<string> */ public readonly array $modifying,
        /** @var list<array{path: string, include: bool}> */ public readonly array $using,
        /** @var list<string> */ public readonly array $like,
        /** @var list<string> */ public readonly array $rules,
        /** @var array<string, mixed> */ public readonly array $context,
        /** @var list<string> */ public readonly array $testing,
        public readonly bool $bake = false,
    ) {}

    /**
     * Deterministic hash of this payload's definition.
     *
     * Used as the bake cache key. Any change to the instruct definition
     * produces a different hash, triggering a fresh AI run.
     */
    public function hash(): string
    {
        $data = [
            $this->description,
            $this->creating,
            $this->modifying,
            $this->using,
            $this->like,
            $this->rules,
            $this->context,
            $this->testing,
        ];

        return hash('xxh128', serialize($data));
    }

    /**
     * Paths from using() that should have contents inlined in the prompt.
     *
     * @return list<string>
     */
    public function includedFiles(): array
    {
        return array_values(array_map(
            fn (array $entry) => $entry['path'],
            array_filter($this->using, fn (array $entry) => $entry['include']),
        ));
    }

    /**
     * Paths from using() that are hints only (not inlined).
     *
     * @return list<string>
     */
    public function hintedFiles(): array
    {
        return array_values(array_map(
            fn (array $entry) => $entry['path'],
            array_filter($this->using, fn (array $entry) => !$entry['include']),
        ));
    }
}
```

---

## 6. `src/AI/Prompts/InstructPromptBuilder.php`

Builds the prompt string. Distinguishes between hint-only files (listed by path) and inlined files (contents embedded). Includes a soft scope constraint in the prompt to keep the AI focused.

```php
<?php

declare(strict_types=1);

namespace Compose\AI\Prompts;

use Compose\Payloads\InstructPayload;
use Compose\RecipeContext;

class InstructPromptBuilder
{
    /**
     * Build the full prompt from the payload.
     *
     * Output structure (sections omitted when empty):
     *
     *   ## Task
     *   {description}
     *
     *   ## Scope
     *   Focus on the files listed below. Do not modify other files unless
     *   strictly necessary to complete the task.
     *
     *   Files to create:
     *   - path/to/new.php
     *
     *   Files to modify:
     *   - path/to/existing.php
     *
     *   ## Context Files
     *   These files are relevant — read them for context:
     *   - app/Models/User.php
     *   - app/Models/Team.php
     *   (hint-only: listed by path, AI tool reads them itself)
     *
     *   ### app/Services/TeamService.php (inlined)
     *   ```php
     *   {file contents read from disk}
     *   ```
     *   (include: true files are inlined with contents)
     *
     *   ## Style References
     *   Match the patterns and conventions in these files:
     *   ### app/Livewire/ProfilePage.php
     *   ```php
     *   {contents}
     *   ```
     *   (like() files are always inlined)
     *
     *   ## Tests
     *   Create or update these test files: ...
     *
     *   ## Rules
     *   - rule 1
     *   - rule 2
     *
     *   ## Project Context
     *   framework: Livewire
     *   php: 8.3
     *
     * Missing files produce a "(file not found)" note rather than failing.
     * This is intentional — the file might be one the AI is about to create,
     * or it might be optional context.
     */
    public function build(InstructPayload $payload, RecipeContext $context): string;

    /**
     * Read a file relative to the working directory. Returns null if not found.
     */
    private function readFile(string $path, RecipeContext $context): ?string;

    /**
     * Detect language identifier for fenced code blocks.
     * php, json, yaml, blade.php → blade, js, ts, vue, etc.
     */
    private function languageForExtension(string $path): string;
}
```

---

## 7. `src/AI/Bake/PatchManager.php`

Git-based patch caching. Critical detail: **new files must be included in patches.** `git diff` only shows tracked files. Before generating a patch, stage untracked files with `git add -N` (intent to add) so they appear in the diff. Reset after saving.

```php
<?php

declare(strict_types=1);

namespace Compose\AI\Bake;

use Compose\Execution\ActionResult;
use Compose\Execution\ProcessExecutor;
use Compose\RecipeContext;

class PatchManager
{
    private const string BAKE_DIR = '.compose/baked';

    public function __construct(
        private readonly ProcessExecutor $executor,
        private readonly RecipeContext $context,
    ) {}

    /**
     * Check if a baked patch exists for the given hash.
     */
    public function has(string $hash): bool;

    /**
     * Try to apply a baked patch.
     *
     * Returns:
     *   - success if applied cleanly
     *   - success with "already applied" message if it's a no-op
     *   - failure if patch conflicts (caller should fall through to AI)
     *
     * Detection logic:
     *   1. Read .compose/baked/{hash}.patch
     *   2. If patch is empty → no-op, return success
     *   3. `git apply --check {patch}` → if clean, `git apply {patch}`, return success
     *   4. `git apply --reverse --check {patch}` → if clean, already applied, return success
     *   5. Otherwise → conflict, return failure with explanation
     */
    public function apply(string $hash): ActionResult;

    /**
     * Capture current changes as a patch and save it.
     *
     * Called after the AI CLI finishes. Must capture BOTH modified tracked
     * files and newly created untracked files.
     *
     * Flow:
     *   1. `git add -N .` — stage intent-to-add for untracked files so they
     *      appear in `git diff`. Does not stage content, just marks them.
     *   2. `git diff` — capture the full diff including new files.
     *   3. `git reset` — unstage the intent-to-add markers. Working tree
     *      is unchanged; only the index is restored.
     *   4. Write diff to .compose/baked/{hash}.patch
     *   5. Create .compose/baked/ directory if it doesn't exist.
     *   6. Return the patch contents for logging.
     *
     * If the diff is empty, writes an empty file so has() returns true
     * and future runs skip the AI without re-running it.
     */
    public function save(string $hash): string;

    /**
     * Snapshot the current working tree state.
     *
     * Called BEFORE the AI runs. Returns an opaque token that diffAgainst()
     * uses to isolate this instruct block's changes from prior changes
     * in the same step.
     *
     * Implementation: `git stash create` — creates a stash commit without
     * modifying the working tree or index. Returns the stash SHA, or empty
     * string if the tree is clean.
     */
    public function snapshot(): string;

    /**
     * Get the diff of changes made since the given snapshot.
     *
     * If $baseline is empty (tree was clean), equivalent to `git diff`.
     * Otherwise: `git diff {baseline}` to see only the new changes.
     *
     * Includes untracked files via the same add -N trick as save().
     */
    public function diffSince(string $baseline): string;

    /**
     * Get list of files changed since the given snapshot.
     *
     * Combines `git diff --name-only` (modified tracked files) with
     * `git ls-files --others --exclude-standard` (untracked/new files).
     *
     * @return list<string>
     */
    public function changedFilesSince(string $baseline): array;

    /**
     * Ensure the bake directory exists.
     */
    private function ensureBakeDirectory(): void;

    private function patchPath(string $hash): string
    {
        $cwd = $this->context->workingDirectory ?? '.';

        return rtrim($cwd, '/\\') . DIRECTORY_SEPARATOR
             . self::BAKE_DIR . DIRECTORY_SEPARATOR . $hash . '.patch';
    }

    /**
     * Run a git command in the working directory and return the result.
     *
     * @param list<string> $args  Arguments after 'git'.
     */
    private function git(string ...$args): ActionResult;
}
```

---

## 8. `src/AI/InstructResult.php`

```php
<?php

declare(strict_types=1);

namespace Compose\AI;

class InstructResult
{
    public function __construct(
        /** @var list<string> Files created or modified by the AI. */
        public readonly array $affectedFiles,
        /** The AI CLI's stdout (captured, not streamed to terminal). */
        public readonly string $output,
        /** Whether the result came from a baked patch rather than live AI. */
        public readonly bool $fromBake,
    ) {}
}
```

---

## 9. `src/Actions/AI/InstructAction.php`

The orchestrator. Uses `PatchManager::snapshot()` to isolate changes from each instruct block, so multiple instructs in a step don't bleed into each other's patches.

```php
<?php

declare(strict_types=1);

namespace Compose\Actions\AI;

use Compose\Actions\Action;
use Compose\AI\Bake\PatchManager;
use Compose\AI\CLIAgent;
use Compose\AI\InstructResult;
use Compose\AI\Prompts\InstructPromptBuilder;
use Compose\Enums\InstructOperation;
use Compose\Execution\ActionResult;
use Compose\Execution\ProcessExecutor;
use Compose\Payloads\InstructPayload;
use Compose\RecipeContext;

class InstructAction extends Action
{
    /** @var list<string> Files changed by this instruct, for rollback */
    private array $affectedFiles = [];

    /** @var list<string> Subset of affectedFiles that existed before AI ran */
    private array $preExisting = [];

    /** @var list<string> Subset of affectedFiles that were newly created */
    private array $created = [];

    /** @var string Snapshot token from PatchManager, for diff isolation */
    private string $baseline = '';

    public function __construct(
        public readonly InstructPayload $payload,
    ) {}

    #[\Override]
    public function type(): InstructOperation
    {
        return InstructOperation::Instruct;
    }

    /**
     * Execution flow:
     *
     * 1. Resolve AI agent from RecipeContext.
     *    Check CLIAgent::isAvailable(). If not:
     *      → return ActionResult::failure() with install instructions.
     *      The error message comes from AIAgent::installInstructions().
     *
     * 2. If bake is enabled, check PatchManager::has($hash).
     *    If patch exists:
     *      → PatchManager::apply($hash)
     *      → If success: populate affectedFiles from the patch, return success.
     *      → If already applied: return success (no-op).
     *      → If conflict: log warning, continue to step 3 (fall through to AI).
     *        Output: "Baked patch conflicts with local changes. Re-running AI.
     *                 Use --rebake to regenerate the patch."
     *
     * 3. Snapshot: PatchManager::snapshot() → store as $this->baseline.
     *
     * 4. Build prompt: InstructPromptBuilder::build($payload, $context).
     *
     * 5. Execute: CLIAgent::execute($prompt, $context).
     *    If the AI CLI fails (non-zero exit):
     *      → return ActionResult::failure() with the CLI's stderr.
     *
     * 6. Diff: PatchManager::changedFilesSince($baseline).
     *    Partition into preExisting (git tracked) and created (untracked).
     *    Store both for rollback.
     *
     * 7. If bake enabled: PatchManager::save($hash) using diffSince($baseline)
     *    to capture only this instruct's changes, not prior step changes.
     *
     * 8. Return ActionResult::success() with summary:
     *    "AI created 2 files, modified 3 files" + list of paths.
     *    Include full AI CLI output in the result output for verbose display.
     */
    #[\Override]
    public function execute(RecipeContext $context): ActionResult;

    #[\Override]
    public function describe(): string
    {
        $hints = [];

        if ($this->payload->creating !== []) {
            $hints[] = count($this->payload->creating) . ' to create';
        }

        if ($this->payload->modifying !== []) {
            $hints[] = count($this->payload->modifying) . ' to modify';
        }

        $suffix = $hints !== [] ? ' (' . implode(', ', $hints) . ')' : '';

        return "instruct: {$this->payload->description}{$suffix}";
    }

    /**
     * Rollback: undo all changes from this instruct block.
     *
     * For preExisting files: `git checkout -- <file>` to restore original.
     * For created files: unlink() to delete them.
     *
     * Uses the stored affectedFiles/preExisting/created arrays
     * populated during execute().
     */
    #[\Override]
    public function canRollbackDirect(): bool
    {
        return true;
    }

    #[\Override]
    public function rollbackDirect(RecipeContext $context): ActionResult;
}
```

---

## 10. `src/AI/Prompts/CommitMessagePromptBuilder.php`

```php
<?php

declare(strict_types=1);

namespace Compose\AI\Prompts;

class CommitMessagePromptBuilder
{
    /**
     * Build a prompt for a conventional commit message.
     *
     * Includes the diff and the step name. Instructs the AI to output
     * ONLY the commit message — no explanation, no markdown fences,
     * no preamble. Subject line max 72 chars, conventional commits format.
     */
    public function build(string $diff, string $stepName): string;
}
```

---

## 11. `src/Execution/AICommitMessageGenerator.php`

```php
<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\AI\CLIAgent;
use Compose\AI\Prompts\CommitMessagePromptBuilder;
use Compose\Contracts\CommitMessageGenerator;
use Compose\RecipeContext;
use Compose\Step;

class AICommitMessageGenerator implements CommitMessageGenerator
{
    public function __construct(
        private readonly CLIAgent $agent,
        private readonly RecipeContext $context,
        private readonly CommitMessagePromptBuilder $promptBuilder = new CommitMessagePromptBuilder,
        private readonly DefaultCommitMessageGenerator $fallback = new DefaultCommitMessageGenerator,
    ) {}

    /**
     * Flow:
     *   1. `git diff --staged` via ProcessExecutor
     *   2. If empty → fall back to default
     *   3. Build prompt via CommitMessagePromptBuilder
     *   4. CLIAgent::prompt() — one-shot, no file editing
     *   5. Clean response: trim whitespace, strip markdown fences and
     *      backticks if present (AI sometimes wraps output)
     *   6. If result is empty or AI failed → fall back to default
     *
     * @param ActionResult[] $actionResults
     */
    #[\Override]
    public function generate(Step $step, array $actionResults): string;
}
```

---

## 12. Wire Into `Step`

Add to `src/Step.php`:

```php
use Compose\Actions\AI\InstructAction;
use Compose\Builders\InstructBuilder;

/**
 * Delegate a task to an AI CLI tool.
 *
 * The AI runs in the working directory with full filesystem access.
 * Compose captures changes via git for rollback and optional patch caching.
 *
 * @param Closure(InstructBuilder): void|null $callback
 */
public function instruct(string $description, ?Closure $callback = null): static
{
    $builder = new InstructBuilder;

    if ($callback !== null) {
        $callback($builder);
    }

    $this->operations[] = new InstructAction($builder->toPayload($description));

    return $this;
}
```

---

## 13. Wire Into `Compose` and `RecipeContext`

**`Compose`:** Replace `$aiProvider` / `$aiModel` / `$aiAgent` with a single field:

```php
protected AIAgent $aiAgent = AIAgent::Claude;

public function ai(AIAgent $agent): static
{
    $this->aiAgent = $agent;

    return $this;
}
```

Pass it through to `RecipeContext` in `getContext()` and `getBaseContext()`.

**`RecipeContext`:** Add `aiAgent` parameter:

```php
public function __construct(
    public readonly string $phpBinary = 'php',
    public readonly string $composerBinary = 'composer',
    public readonly string $gitBinary = 'git',
    public readonly Node $nodeManager = Node::Npm,
    public readonly ?string $workingDirectory = null,
    public readonly AIAgent $aiAgent = AIAgent::Claude,
) {}
```

Update `withWorkingDirectory()` to carry `aiAgent` to the new instance.

**Cleanup:** Delete `src/Contracts/AI.php`, `src/Enums/Anthropic.php`, `src/Enums/OpenAI.php`. Remove `Compose::isUsingAI()`.

---

## 14. Wire Smart Commits Into `Runner`

In `Runner::run()`, before the step loop:

```php
if ($config->smartCommit) {
    $agent = new CLIAgent($this->executor, $config->context->aiAgent);

    if ($agent->isAvailable()) {
        $this->commitMessageGenerator = new AICommitMessageGenerator(
            $agent,
            $config->context,
        );
    }
}
```

If the AI CLI isn't available, smart commit silently falls back to the default generator. No error — the user opted in but the tool isn't there.

---

## 15. CLI Flags

Add to `RunCommand`:

```php
#[Option(name: 'no-ai', description: 'Skip all AI operations (instruct blocks become no-ops, smart commits use default messages)')]
bool $noAi = false,

#[Option(name: 'rebake', description: 'Regenerate all baked patches (forces AI re-run for baked instruct blocks)')]
bool $rebake = false,
```

**`--no-ai`:** Thread to `RecipeConfig`. `InstructAction::execute()` checks it and returns:
```
ActionResult::success(output: "Skipped (--no-ai): {description}")
```
`Runner` doesn't wire up `AICommitMessageGenerator`.

**`--rebake`:** Thread to `RecipeConfig`. `InstructAction::execute()` deletes existing patch before checking bake, forcing a fresh AI run and new patch.

Add to `RecipeConfig`:

```php
public readonly bool $noAi = false,
public readonly bool $rebake = false,
```

Update `RecipeConfig::withOverrides()` to accept these.

---

## 16. Event Handling for Instruct Actions

In `RunCommand::registerEventListeners()`, instruct actions should display differently from normal actions. The AI CLI captures output silently — show a brief summary in normal mode, full output in verbose.

```php
// In the ActionCompleted listener, after existing logic:
if ($event->action instanceof InstructAction) {
    // Normal mode: just the summary line (already handled by default)
    // Verbose mode: show the AI's output
    if ($io->isVerbose() && $event->result->output !== '') {
        $lines = explode("\n", trim($event->result->output));
        $truncated = count($lines) > 20
            ? [...array_slice($lines, 0, 20), '... (' . count($lines) . ' lines total)']
            : $lines;

        foreach ($truncated as $line) {
            $io->text("    <fg=gray>{$line}</>");
        }
    }
}

// In the ActionFailed listener, for instruct actions:
if ($event->action instanceof InstructAction && !$event->warned) {
    // Show install instructions if the failure looks like a missing binary
    if (str_contains($event->result->errorOutput, 'not found')
        || str_contains($event->result->errorOutput, 'not recognized')) {
        $io->text("    <fg=yellow>{$event->action->installInstructions()}</>");
    }
}
```

Add a convenience method to `InstructAction`:

```php
public function installInstructions(): string
{
    // Resolved from context at call time
    return $this->context()->aiAgent->installInstructions();
}
```

---

## Implementation Order

1. **Enums:** `AIAgent` (with `buildCommand`, `installInstructions`), `InstructOperation`.
2. **Payloads:** `InstructPayload` (with `hash()`, `includedFiles()`, `hintedFiles()`).
3. **Builder:** `InstructBuilder` — pure collection, fully testable standalone.
4. **Prompt builder:** `InstructPromptBuilder` — string construction + file reading.
5. **PatchManager** — git operations. Test with `InteractsWithFilesystem` + temp git repos. **Pay special attention to `git add -N` for untracked files and `snapshot()`/`diffSince()` for isolation.**
6. **CLIAgent** — thin ProcessExecutor wrapper. Test with `ProcessExecutor::fake()`.
7. **InstructAction** — the orchestrator. Ties everything together.
8. **Wire into `Step`, `Compose`, `RecipeContext`.** Clean up old AI enums/contracts.
9. **CLI flags:** `--no-ai`, `--rebake` in `RunCommand` and `RecipeConfig`.
10. **Smart commits:** `CommitMessagePromptBuilder` + `AICommitMessageGenerator`.
11. **Event handling** in `RunCommand` for instruct-specific output.

---

## Critical Implementation Notes

### Untracked files in patches

`git diff` does not include untracked (new) files. Before any diff or patch operation:
```
git add -N .        # mark untracked files as "intent to add"
git diff            # now includes new files as "new file" hunks
git reset           # undo the staging, working tree unchanged
```
This applies to both `PatchManager::save()` and `PatchManager::diffSince()`. Without this, bake patches silently drop newly created files and replaying them creates incomplete projects.

### Isolating multiple instructs per step

Two instruct blocks in one step:
```php
$step
    ->instruct('Create the models')
    ->instruct('Create the tests');
```
Without isolation, the second instruct's `changedFilesSince()` sees both instructs' changes. Its bake patch would include the first instruct's files. On replay, the first patch applies its files, then the second patch tries to apply them again → conflict.

Fix: `PatchManager::snapshot()` uses `git stash create` to capture a reference point before each instruct runs. `diffSince()` and `changedFilesSince()` diff against that reference, not against HEAD. This gives each instruct block its own isolated diff.

### Bake cache invalidation messaging

When a baked patch fails to apply, the error must be specific:
```
Baked patch for "Create the dashboard" (hash: a1b2c3) cannot be applied cleanly.
This usually means files were modified after the patch was generated.
Falling back to AI. Use --rebake to regenerate all patches.
```
Generic "patch failed" messages will cause support questions.

### AI CLI output suppression

The AI CLI tools produce verbose output (streaming thoughts, file edits, progress). This should NOT stream to the terminal during recipe execution — it would interleave with Compose's structured step/action output.

`ProcessExecutor` already captures stdout/stderr. The captured output goes into `ActionResult::$output` and is shown only in verbose mode (`-v`). In normal mode, the user sees:
```
  ▸ instruct: Create the dashboard (2 to create, 1 to modify)
  ✓ instruct: Create the dashboard (2 to create, 1 to modify) (45.2s)
```

### Preflight for instruct actions

`InstructAction` should implement `preflight()` to check if the AI CLI is available before attempting execution. This catches "binary not found" before the prompt is even built.

```php
#[\Override]
public function preflight(): ?PendingCommand
{
    return new PendingCommand($this->context()->aiAgent->binary(), '--version');
}
```

This leverages the existing preflight system in `ExecuteActions` — the check runs once per action class, and the error path is already handled.

---

## Example Recipes

### Minimal

```php
$step->instruct('Add soft deletes to the Team model and update the migration');
```

### With context hints and bake

```php
$step->instruct('Create a team management dashboard', fn (InstructBuilder $i) => $i
    ->creating('app/Livewire/Dashboard.php')
    ->creating('app/Livewire/TeamMemberList.php')
    ->modifying('routes/web.php')
    ->using('app/Models/User.php')
    ->using('app/Models/Team.php')
    ->using('app/Services/TeamService.php', include: true)  // inline this one
    ->like('app/Livewire/ProfilePage.php')
    ->rules([
        'Use Livewire 3 full-page component syntax',
        'All properties must be typed',
    ])
    ->testing('tests/Feature/DashboardTest.php')
    ->bake()
);
```

### Mixed manual + AI

```php
$recipe
    ->step('Install', function (Step $step): void {
        $step->composer(install: ['livewire/livewire']);
    })
    ->step('Build', function (Step $step): void {
        $step
            ->instruct('Create the dashboard', fn (InstructBuilder $i) => $i
                ->creating('app/Livewire/Dashboard.php')
                ->using('app/Models/Team.php')
                ->bake()
            )
            ->verify(fn () => file_exists('app/Livewire/Dashboard.php'));
    });
```

### Using aider

```php
compose('My App')
    ->ai(AIAgent::Aider)
    ->step('API', function (Step $step): void {
        $step->instruct('Create a REST API for team management');
    });
```

---

## Prerequisites: Changes to Existing Code

These changes to the existing codebase are required before or during implementation. They unblock the new code.

### ProcessExecutor access in direct-execution actions

`InstructAction::execute()` needs a `ProcessExecutor` to construct `CLIAgent` and `PatchManager`. Direct-execution actions currently receive only `RecipeContext` — they have no way to shell out.

Add an optional executor to the base `Action` class:

```php
// In src/Actions/Action.php:

protected ?ProcessExecutor $processExecutor = null;

public function withExecutor(ProcessExecutor $executor): static
{
    $this->processExecutor = $executor;

    return $this;
}

protected function executor(): ProcessExecutor
{
    return $this->processExecutor ?? throw new RuntimeException(
        'Action requires a ProcessExecutor. The runner must call withExecutor() before execution.',
    );
}
```

Wire it in `ExecuteActions::handle()`, alongside the existing `withContext()` call:

```php
// In src/Execution/Pipes/ExecuteActions.php, in the foreach loop:
$action->withContext($context->recipeContext);
$action->withExecutor($context->executor);   // add this line
```

Only `InstructAction` uses `executor()` initially. Other direct actions continue using PHP-native I/O.

### Cleanup of old AI contracts and enums

The following files should be deleted:

- `src/Contracts/AI.php` — the `BackedEnum` interface for HTTP AI providers
- `src/Enums/Anthropic.php` — Anthropic model enum
- `src/Enums/OpenAI.php` — OpenAI model enum

The following references in `src/Compose.php` must be updated:

```php
// DELETE these:
use Compose\Contracts\AI;
protected ?string $aiProvider = null;
protected AI|string|null $aiModel = null;
public function isUsingAI(): bool { ... }

// DELETE the old ai() method and REPLACE with:
protected AIAgent $aiAgent = AIAgent::Claude;

public function ai(AIAgent $agent): static
{
    $this->aiAgent = $agent;

    return $this;
}
```

Update `Compose::getContext()` and `getBaseContext()` to pass `aiAgent: $this->aiAgent` to the `RecipeContext` constructor.

Update `RecipeContext::withWorkingDirectory()` to include `aiAgent` in the new instance.

The `commit()` method's `smart` parameter is unchanged — it already writes to `$this->commitUsingAI` which flows to `RecipeConfig::$smartCommit`.

### RecipeConfig additions

Add `noAi` and `rebake` to `RecipeConfig`:

```php
public function __construct(
    // ... existing params ...
    public readonly bool $noAi = false,
    public readonly bool $rebake = false,
) {}
```

Update `withOverrides()` to accept `?bool $noAi = null, ?bool $rebake = null`.

---

## Tests

Create these test files following existing conventions (Pest, `describe()` blocks, `InteractsWithFilesystem` for file ops, `ProcessExecutor::fake()` for commands).

### `tests/Unit/InstructBuilderTest.php`

- Builder accumulates `creating()`, `modifying()`, `using()`, `like()`, `rules()`, `with()`, `testing()` correctly
- `using()` defaults to `include: false`; `using('file', include: true)` sets it
- `rules()` and `with()` merge across multiple calls (not replace)
- `toPayload()` produces correct `InstructPayload` with all fields
- `bake()` flag propagates

### `tests/Unit/InstructPayloadTest.php`

- `hash()` is deterministic (same inputs → same hash)
- `hash()` changes when any definition field changes (description, file lists, rules, context)
- `hash()` does NOT change when `bake` flag changes (execution option, not definition — wait, bake IS excluded since it's not in the hash data array. Verify this.)
- `includedFiles()` returns only paths where `include: true`
- `hintedFiles()` returns only paths where `include: false`

### `tests/Unit/InstructPromptBuilderTest.php`

- Prompt contains the description under `## Task`
- `creating()` files listed under `## Scope`
- `modifying()` files listed under `## Scope`
- Hint-only `using()` files listed by path (contents not inlined)
- `using(include: true)` files have contents inlined with fenced code blocks
- `like()` files always inlined with contents
- Missing files produce a "(file not found)" note, not an exception
- `rules()` included verbatim
- `with()` context pairs included
- Empty sections are omitted entirely (no empty headers)
- Uses `InteractsWithFilesystem` to create fixture files on disk

### `tests/Unit/CLIAgentTest.php`

- `execute()` calls `ProcessExecutor` with correct command from `AIAgent::buildCommand()`
- `execute()` uses the agent's timeout
- Long prompts (>4096 bytes) are written to a temp file and cleaned up after
- Temp file cleanup happens even if execution fails
- `prompt()` calls `buildSimpleCommand()` with shorter timeout
- `isAvailable()` returns true/false based on `--version` exit code
- Uses `ProcessExecutor::fake()`

### `tests/Unit/PatchManagerTest.php`

This needs real git repos in temp directories. Use `InteractsWithFilesystem` plus git init/commit in `beforeEach`.

- `has()` returns false when no patch exists
- `save()` creates the `.compose/baked/` directory and writes the patch file
- `save()` captures untracked (new) files via `git add -N` trick
- `save()` writes empty file when diff is empty
- `has()` returns true after `save()`
- `apply()` applies a clean patch and returns success
- `apply()` detects already-applied patch and returns success (no-op)
- `apply()` returns failure on conflict
- `snapshot()` returns a baseline token
- `changedFilesSince()` returns only files changed after the snapshot, not before
- `diffSince()` includes new files (untracked)

### `tests/Unit/InstructActionTest.php`

- Describe output includes file count hints
- Execute with `--no-ai` returns skip result
- Execute when binary unavailable returns failure with install instructions
- Execute runs prompt builder → CLI agent → captures changed files
- Bake: first run saves patch, second run applies patch and skips AI
- Bake: `--rebake` forces fresh AI run
- Bake: conflicting patch falls through to AI with warning
- Rollback restores modified files and deletes created files
- Multiple instructs in one step get isolated diffs (snapshot/diffSince)
- Uses `ProcessExecutor::fake()` and `InteractsWithFilesystem` with a real git repo

### `tests/Unit/AICommitMessageGeneratorTest.php`

- Falls back to default when diff is empty
- Falls back to default when AI CLI fails
- Strips markdown fences from AI response
- Trims whitespace from AI response
- Uses `ProcessExecutor::fake()`

### `tests/Unit/AIAgentTest.php`

- Each agent produces correct `buildCommand()` output
- Each agent produces correct `buildSimpleCommand()` output
- `installInstructions()` returns non-empty string for each agent
- `timeout()` returns a float > 0
