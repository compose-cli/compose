# TUI Library Comparison for Compose

An evaluation of terminal UI options for the Compose scaffolding tool, focused on replacing `SymfonyStyle` as the rendering/interaction layer.

---

## Core Insight

Symfony Console is two things:

1. **Application framework** — command routing, argument/option parsing, help generation. This part is fine.
2. **Output renderer** — `SymfonyStyle` with `$io->text()`, `$io->section()`, ANSI color tags. This is where the ceiling is.

The current `RunCommand` output is purely sequential line-appending. That caps out when you need:

- Live in-place updates (spinners while `composer install` runs, then replacing with a checkmark)
- A dashboard view showing overall recipe progress alongside current action output
- Interactive step selection/review before execution
- Streaming AI output display for instruct/recovery features
- Interactive error recovery ("Retry? Skip? Abort?" with a proper selector)
- Concurrent visual feedback (spinner on current action + scrolling process output)

---

## Option 1: Laravel Prompts (+ Chewie for custom components)

**What it gives Compose:**

```
Recipe: Laravel SaaS Starter
─────────────────────────────
  ◼ Clone base repository        ✓ done (2.3s)
  ◼ Install dependencies         ⠋ running composer install...
  ◻ Configure environment
  ◻ Install frontend packages
  ◻ Run migrations
```

- `spin()` wraps `ProcessExecutor` calls — shows a spinner while processes run, resolves to a result
- `progress()` for multi-step recipe progress
- `select()` for interactive step selection (instead of `--step=3`)
- `confirm()` for AI review flow (`--review` flag)
- `note()`, `info()`, `warning()`, `error()` for styled output blocks
- Chewie extends this to build a custom live-updating progress dashboard (Model tracks step state, Renderer redraws the full view on each event)

**Pros:**

- Laravel ecosystem standard — users already know it
- Works standalone, no Laravel dependency
- Graceful degradation: falls back to basic text prompts on unsupported terminals
- Chewie enables custom live components without building from scratch

**Cons:**

- **Windows support is incomplete.** Known issues with multi-select, `stty` errors, rendering glitches on native Windows (non-WSL). Real problem for a tool meant for broad distribution.
- Chewie is a small project (one maintainer, limited docs)
- The `spin()` function blocks — can't show spinner + stream process output simultaneously

---

## Option 2: php-tui/php-tui (Full TUI)

**What it gives Compose:**

A full alternate-screen terminal application — dashboard with panels, scrolling log, progress sidebar:

```
┌─ Compose: Laravel SaaS Starter ──────────────────────────┐
│ Steps              │ Current Action                       │
│ ✓ Clone repo       │ $ composer require laravel/sanctum   │
│ ✓ Composer install │   Installing dependencies...         │
│ ▸ Add packages     │   - laravel/sanctum v4.1.2           │
│ ◻ Configure env    │   - Writing lock file                │
│ ◻ Frontend         │   - Generating autoload files        │
│ ◻ Migrations       │                                      │
├────────────────────┴──────────────────────────────────────┤
│ [3/6] 50% complete                          elapsed: 45s  │
└───────────────────────────────────────────────────────────┘
```

**Pros:**

- Maximum visual power — real layouts, widgets, live updates
- Proper event loop for concurrent rendering + process execution
- Most impressive UX possible in a terminal

**Cons:**

- **Native Windows is explicitly unsupported.** Requires `stty`, no Windows implementation.
- Complete paradigm shift — building a terminal application, not a CLI tool
- 277 GitHub stars, early-stage project, one core maintainer
- Overkill for most scaffolding runs (recipe takes 30 seconds, months building the UI)
- Users can't pipe output, redirect to files, or use in CI without an alternate non-TUI mode

---

## Option 3: Termwind (Styled output only)

**What it gives Compose:**

```php
render(<<<HTML
    <div class="mx-2 my-1">
        <div class="px-1 bg-green-500 text-black font-bold">✓ Install dependencies</div>
        <span class="text-gray ml-4">composer require laravel/sanctum</span>
    </div>
HTML);
```

**Pros:**

- Beautiful static output with Tailwind-like syntax
- Cross-platform (just ANSI output, no terminal mode switching)
- Used by Pest, Pint, and other tools in the ecosystem

**Cons:**

- **Output only** — no interactivity, no spinners, no prompts
- Doesn't solve the live-update problem
- Still needs something else for interactive features

---

## Option 4: soloterm/screen (Rendering engine)

[soloterm/screen](https://github.com/soloterm/screen) is a terminal renderer written in pure PHP by Aaron Francis. It powers [Solo for Laravel](https://github.com/soloterm/solo) (1.1k stars), a full TUI that manages multiple dev processes in panels like tmux. The rendering core was extracted into this standalone library.

**What it is:** A virtual terminal buffer. You create a `Screen(80, 24)`, write text + ANSI escape sequences to it, and get back rendered output. It's the rendering *engine*, not a framework.

**What it gives Compose:**

- **Virtual terminal buffers** — create a Screen per panel/section. Process output with ANSI codes stays contained and can't break your layout.
- **Differential rendering** — only changed lines get redrawn. Enables 40 FPS live updates without flicker.
- **Relative positioning** — render a Screen at any cursor position in a parent layout. Multiple screens compose together cleanly.
- **ANSI containment** — `composer install` or `npm install` output goes through a Screen buffer, so escape codes can't corrupt your progress display.
- **Unicode/emoji handling** — proper width calculation for CJK, emoji, grapheme clusters via soloterm/grapheme.

**What it does NOT provide:**

- No input handling (keyboard events, raw terminal mode)
- No layout system (you arrange Screens manually with cursor positioning)
- No interactive prompts (select, confirm, etc.)
- No event loop
- No component abstractions (progress bars, spinners)

**Pros:**

- Pure PHP, single dependency (soloterm/grapheme) — minimal footprint
- Battle-tested rendering (powers Solo, which has 1.1k+ stars and active maintenance)
- **No `stty` dependency** — writes ANSI sequences to stdout. Should work on any terminal that supports ANSI, including Windows Terminal (Windows 10+). Significant advantage over Laravel Prompts and php-tui.
- Solves the hardest rendering problems (ANSI containment, differential updates, cursor positioning, cross-terminal compatibility)
- Active maintainer (Aaron Francis), Laravel ecosystem credibility

**Cons:**

- You build the framework layer yourself — layout, components, event loop
- Not designed for interactive input (need to pair with something else for prompts)
- Relatively new as a standalone library (247 stars, extracted from Solo late 2024)
- Visual testing suite requires macOS + iTerm2/Ghostty (though the library itself is cross-platform)

---

## Option 5: Keep Symfony Console, build a custom rendering layer

Use Symfony Console for commands + use `Symfony\Component\Console\Cursor` and raw ANSI codes for in-place updates. Build a thin progress renderer from scratch.

**Pros:**

- No new dependencies
- Full control, works exactly how you want
- Cross-platform (Symfony Console handles Windows terminal detection)
- No degraded Windows experience

**Cons:**

- Building a rendering engine from scratch (solving problems that soloterm/screen already solves)
- No interactive prompts beyond what Symfony provides (basic ChoiceQuestion)
- Significant effort for something that libraries already solve
- No ANSI containment for process output — harder to prevent escape codes from corrupting your UI

---

## Recommendation

Given that:

1. The tool needs cross-platform support (including Windows)
2. The roadmap includes AI features needing interactive prompts
3. The tool runs short-lived processes (not a persistent dashboard)
4. It lives in the Laravel/PHP ecosystem
5. Live execution display with process output containment is important

**Hybrid approach: soloterm/screen for rendering + Laravel Prompts for interaction, keeping Symfony Console as the application shell.**

### Why this combination

- **soloterm/screen** handles the execution display — live-updating step progress, contained process output panels, differential rendering for smooth updates. No `stty` dependency means it works on Windows Terminal natively.
- **Laravel Prompts** handles the interactive moments — recipe selection, `--review` approval, error recovery prompts ("Retry? Skip? Abort?"), AI interaction flows. Degrades gracefully to basic readline on unsupported terminals.
- **Symfony Console** stays as the application shell — command routing, argument/option parsing, help generation. No reason to replace this.

### Architecture

```
Symfony Console (command routing, args, options)
     └─> Compose Renderer
            ├─ soloterm/screen (execution display: live progress, process output panels)
            ├─ Laravel Prompts (interactive: select, confirm, spin, search)
            └─ Fallback (plain text for CI / piped output / unsupported terminals)
```

This replaces `SymfonyStyle` entirely while keeping the `RunCommand`/`PlanCommand` structure. The event listeners in `RunCommand` would drive Screen buffer updates instead of calling `$io->text()`.

### What the execution display could look like

During `compose run`, soloterm/screen enables a live-updating view:

```
  Compose — Laravel SaaS Starter
  ────────────────────────────────────────────────

  ✓ Clone base repository                    2.3s
  ✓ Install Composer dependencies           14.1s
  ⠋ Install frontend packages
    ┊ npm install
    ┊ added 847 packages in 12s
    ┊ 128 packages are looking for funding
  ◻ Configure environment
  ◻ Run migrations
  ◻ Seed database

  [3/6] ██████████░░░░░░░░░░  50%    elapsed: 31s
```

The spinner and progress bar update in-place via differential rendering. The npm output scrolls within its contained Screen buffer without pushing the rest of the UI around. When the step completes, the output collapses to a single checkmark line.

### Interactive moments (Laravel Prompts)

For step selection, error recovery, and AI review:

```
 ┌ Which step would you like to run? ───────────────┐
 │ ○ Clone base repository                          │
 │ ○ Install Composer dependencies                  │
 │ ● Install frontend packages                      │
 │ ○ Configure environment                          │
 │ ○ Run migrations                                 │
 │ ○ Seed database                                  │
 └──────────────────────────────────────────────────┘
```

```
 ┌ Action failed: composer require foo/bar ──────────┐
 │ Error: Package foo/bar not found                  │
 │                                                    │
 │ ● Retry this action                               │
 │ ○ Skip and continue                               │
 │ ○ Abort recipe                                    │
 │ ○ Ask AI to diagnose                              │
 └───────────────────────────────────────────────────┘
```
