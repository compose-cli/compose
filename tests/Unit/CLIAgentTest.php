<?php

declare(strict_types=1);

use Compose\AI\CLIAgent;
use Compose\Enums\AIAgent;
use Compose\Execution\ActionResult;
use Compose\Execution\ProcessExecutor;

afterEach(function (): void {
    ProcessExecutor::reset();
});

describe('CLIAgent', function (): void {
    // -------------------------------------------------------------------
    // execute
    // -------------------------------------------------------------------

    describe('execute', function (): void {
        it('executes the AI command via ProcessExecutor', function (): void {
            ProcessExecutor::fake();

            $agent = new CLIAgent(new ProcessExecutor, AIAgent::Claude);
            $result = $agent->execute('Build a widget', context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();

            ProcessExecutor::assertExecuted(['claude', '-p', 'Build a widget', '--allowedTools', 'edit,write,bash', '--output-format', 'text']);
        });

        it('uses the agent timeout', function (): void {
            ProcessExecutor::fake();

            $agent = new CLIAgent(new ProcessExecutor, AIAgent::Claude);
            $agent->execute('task', context(workingDirectory: $this->tempPath));

            ProcessExecutor::assertExecutedWithTimeout(
                ['claude', '*'],
                AIAgent::Claude->timeout(),
            );
        });

        it('writes large prompts to a temp file', function (): void {
            $largePrompt = str_repeat('x', 5000);

            ProcessExecutor::fake();

            $agent = new CLIAgent(new ProcessExecutor, AIAgent::Claude);
            $agent->execute($largePrompt, context(workingDirectory: $this->tempPath));

            ProcessExecutor::assertExecuted(['claude', '-p', '--input-file', '*']);
        });

        it('cleans up temp file after execution', function (): void {
            $largePrompt = str_repeat('x', 5000);

            ProcessExecutor::fake();

            $agent = new CLIAgent(new ProcessExecutor, AIAgent::Claude);
            $agent->execute($largePrompt, context(workingDirectory: $this->tempPath));

            $files = glob($this->tempPath.DIRECTORY_SEPARATOR.'.compose-prompt-*');
            expect($files)->toBe([]);
        });

        it('cleans up temp file even on failure', function (): void {
            $largePrompt = str_repeat('x', 5000);

            ProcessExecutor::fake([
                '*' => ActionResult::failure(),
            ]);

            $agent = new CLIAgent(new ProcessExecutor, AIAgent::Claude);
            $agent->execute($largePrompt, context(workingDirectory: $this->tempPath));

            $files = glob($this->tempPath.DIRECTORY_SEPARATOR.'.compose-prompt-*');
            expect($files)->toBe([]);
        });
    });

    // -------------------------------------------------------------------
    // prompt
    // -------------------------------------------------------------------

    describe('prompt', function (): void {
        it('calls buildSimpleCommand', function (): void {
            ProcessExecutor::fake();

            $agent = new CLIAgent(new ProcessExecutor, AIAgent::Claude);
            $agent->prompt('Generate a commit message', context(workingDirectory: $this->tempPath));

            ProcessExecutor::assertExecuted(['claude', '-p', 'Generate a commit message', '*']);
        });
    });

    // -------------------------------------------------------------------
    // isAvailable
    // -------------------------------------------------------------------

    describe('isAvailable', function (): void {
        it('returns true when binary responds', function (): void {
            ProcessExecutor::fake([
                'claude --version' => ActionResult::success(output: 'claude 1.0'),
            ]);

            $agent = new CLIAgent(new ProcessExecutor, AIAgent::Claude);

            expect($agent->isAvailable())->toBeTrue();
        });

        it('returns false when binary is missing', function (): void {
            ProcessExecutor::fake([
                'claude --version' => ActionResult::failure(errorOutput: 'not found'),
            ]);

            $agent = new CLIAgent(new ProcessExecutor, AIAgent::Claude);

            expect($agent->isAvailable())->toBeFalse();
        });
    });

    // -------------------------------------------------------------------
    // installInstructions
    // -------------------------------------------------------------------

    it('returns install instructions from the agent', function (): void {
        $agent = new CLIAgent(new ProcessExecutor, AIAgent::Claude);

        expect($agent->installInstructions())->toContain('claude');
    });
});
