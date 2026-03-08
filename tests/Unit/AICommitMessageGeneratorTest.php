<?php

declare(strict_types=1);

use Compose\AI\CLIAgent;
use Compose\Enums\AIAgent;
use Compose\Execution\ActionResult;
use Compose\Execution\AICommitMessageGenerator;
use Compose\Execution\ProcessExecutor;
use Compose\Step;

afterEach(function (): void {
    ProcessExecutor::reset();
});

describe('AICommitMessageGenerator', function (): void {
    it('falls back to default when staged diff is empty', function (): void {
        ProcessExecutor::fake([
            'git diff --staged' => ActionResult::success(output: ''),
        ]);

        $executor = new ProcessExecutor;
        $agent = new CLIAgent($executor, AIAgent::Claude);
        $ctx = context(workingDirectory: $this->tempPath);
        $generator = new AICommitMessageGenerator($agent, $ctx, $executor);

        $step = new Step('Install packages');
        $message = $generator->generate($step, []);

        expect($message)->toBe('compose: Install packages');
    });

    it('falls back to default when AI fails', function (): void {
        ProcessExecutor::fake([
            'git diff --staged' => ActionResult::success(output: 'diff --git a/file.php'),
            '*' => ActionResult::failure(errorOutput: 'API error'),
        ]);

        $executor = new ProcessExecutor;
        $agent = new CLIAgent($executor, AIAgent::Claude);
        $ctx = context(workingDirectory: $this->tempPath);
        $generator = new AICommitMessageGenerator($agent, $ctx, $executor);

        $step = new Step('Build feature');
        $message = $generator->generate($step, []);

        expect($message)->toBe('compose: Build feature');
    });

    it('returns AI-generated message on success', function (): void {
        ProcessExecutor::fake([
            'git diff --staged' => ActionResult::success(output: 'diff --git a/file.php'),
            '*' => ActionResult::success(output: 'feat: add user authentication'),
        ]);

        $executor = new ProcessExecutor;
        $agent = new CLIAgent($executor, AIAgent::Claude);
        $ctx = context(workingDirectory: $this->tempPath);
        $generator = new AICommitMessageGenerator($agent, $ctx, $executor);

        $step = new Step('Auth');
        $message = $generator->generate($step, []);

        expect($message)->toBe('feat: add user authentication');
    });

    it('strips markdown fences from AI response', function (): void {
        ProcessExecutor::fake([
            'git diff --staged' => ActionResult::success(output: 'diff --git a/file.php'),
            '*' => ActionResult::success(output: "```\nfeat: add auth\n```"),
        ]);

        $executor = new ProcessExecutor;
        $agent = new CLIAgent($executor, AIAgent::Claude);
        $ctx = context(workingDirectory: $this->tempPath);
        $generator = new AICommitMessageGenerator($agent, $ctx, $executor);

        $step = new Step('Auth');
        $message = $generator->generate($step, []);

        expect($message)->toBe('feat: add auth');
    });

    it('strips backticks from AI response', function (): void {
        ProcessExecutor::fake([
            'git diff --staged' => ActionResult::success(output: 'diff --git a/file.php'),
            '*' => ActionResult::success(output: '`feat: add auth`'),
        ]);

        $executor = new ProcessExecutor;
        $agent = new CLIAgent($executor, AIAgent::Claude);
        $ctx = context(workingDirectory: $this->tempPath);
        $generator = new AICommitMessageGenerator($agent, $ctx, $executor);

        $step = new Step('Auth');
        $message = $generator->generate($step, []);

        expect($message)->toBe('feat: add auth');
    });

    it('trims whitespace from AI response', function (): void {
        ProcessExecutor::fake([
            'git diff --staged' => ActionResult::success(output: 'diff --git a/file.php'),
            '*' => ActionResult::success(output: "  feat: add auth  \n\n"),
        ]);

        $executor = new ProcessExecutor;
        $agent = new CLIAgent($executor, AIAgent::Claude);
        $ctx = context(workingDirectory: $this->tempPath);
        $generator = new AICommitMessageGenerator($agent, $ctx, $executor);

        $step = new Step('Auth');
        $message = $generator->generate($step, []);

        expect($message)->toBe('feat: add auth');
    });

    it('falls back when AI returns empty after cleaning', function (): void {
        ProcessExecutor::fake([
            'git diff --staged' => ActionResult::success(output: 'diff --git a/file.php'),
            '*' => ActionResult::success(output: "```\n```"),
        ]);

        $executor = new ProcessExecutor;
        $agent = new CLIAgent($executor, AIAgent::Claude);
        $ctx = context(workingDirectory: $this->tempPath);
        $generator = new AICommitMessageGenerator($agent, $ctx, $executor);

        $step = new Step('Build');
        $message = $generator->generate($step, []);

        expect($message)->toBe('compose: Build');
    });
});
