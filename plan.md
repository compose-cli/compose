# Compose — Planned Changes & Refactors

This document tracks all planned refactors, fixes, and new features for Compose, organized by priority. Each item includes the rationale and a checklist of subtasks.

---

## Priority 1 and 2 tasks complete

## Priority 3: New Features — Deterministic Actions

These extend the `Step` API with deterministic operations. Each is a new Action class + Step method + tests. They're listed roughly in order of implementation difficulty and user value.

### 3.1, 3.2, 3.3, 3.4, 3.5 tasks complete

## Priority 4 - complete

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

## Priority 6: Control Flow & Verification - complete

### 6.1 Conditional Execution - complete

### 6.2 Verification Gates - complete

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