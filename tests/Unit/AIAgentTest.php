<?php

declare(strict_types=1);

use Compose\Enums\AIAgent;

describe('AIAgent', function (): void {
    // -------------------------------------------------------------------
    // buildCommand
    // -------------------------------------------------------------------

    describe('buildCommand', function (): void {
        it('builds a claude command with inline prompt', function (): void {
            $cmd = AIAgent::Claude->buildCommand('do stuff');

            expect($cmd)->toContain('claude', '-p', 'do stuff');
            expect($cmd)->toContain('--allowedTools', 'edit,write,bash');
            expect($cmd)->toContain('--output-format', 'text');
        });

        it('builds a claude command with prompt file', function (): void {
            $cmd = AIAgent::Claude->buildCommand('do stuff', '/tmp/prompt.md');

            expect($cmd)->toContain('--input-file', '/tmp/prompt.md');
            expect($cmd)->not->toContain('do stuff');
        });

        it('builds an aider command with inline prompt', function (): void {
            $cmd = AIAgent::Aider->buildCommand('do stuff');

            expect($cmd)->toContain('aider', '--message', 'do stuff');
            expect($cmd)->toContain('--yes-always', '--no-git');
        });

        it('builds an aider command with prompt file', function (): void {
            $cmd = AIAgent::Aider->buildCommand('do stuff', '/tmp/prompt.md');

            expect($cmd)->toContain('--message-file', '/tmp/prompt.md');
        });

        it('builds a codex command with inline prompt', function (): void {
            $cmd = AIAgent::Codex->buildCommand('do stuff');

            expect($cmd)->toContain('codex', '-q', 'do stuff');
            expect($cmd)->toContain('--approval-mode', 'full-auto');
        });

        it('builds a codex command with prompt file', function (): void {
            $cmd = AIAgent::Codex->buildCommand('do stuff', '/tmp/prompt.md');

            expect($cmd)->toContain('--input-file', '/tmp/prompt.md');
        });
    });

    // -------------------------------------------------------------------
    // buildSimpleCommand
    // -------------------------------------------------------------------

    describe('buildSimpleCommand', function (): void {
        it('builds a claude simple command', function (): void {
            $cmd = AIAgent::Claude->buildSimpleCommand('summarize this');

            expect($cmd)->toContain('claude', '-p', 'summarize this');
            expect($cmd)->toContain('--allowedTools', '');
        });

        it('builds an aider simple command', function (): void {
            $cmd = AIAgent::Aider->buildSimpleCommand('summarize this');

            expect($cmd)->toContain('aider', '--message', 'summarize this');
            expect($cmd)->toContain('--no-auto-commits');
        });

        it('builds a codex simple command', function (): void {
            $cmd = AIAgent::Codex->buildSimpleCommand('summarize this');

            expect($cmd)->toContain('codex', '-q', 'summarize this');
            expect($cmd)->toContain('--approval-mode', 'suggest');
        });
    });

    // -------------------------------------------------------------------
    // Other methods
    // -------------------------------------------------------------------

    it('returns the binary name from value', function (): void {
        expect(AIAgent::Claude->binary())->toBe('claude');
        expect(AIAgent::Aider->binary())->toBe('aider');
        expect(AIAgent::Codex->binary())->toBe('codex');
    });

    it('returns a positive timeout', function (): void {
        foreach (AIAgent::cases() as $agent) {
            expect($agent->timeout())->toBeGreaterThan(0.0);
        }
    });

    it('returns non-empty install instructions for each agent', function (): void {
        foreach (AIAgent::cases() as $agent) {
            expect($agent->installInstructions())->not->toBeEmpty();
        }
    });
});
