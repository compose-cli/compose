<?php

declare(strict_types=1);

namespace Compose\Enums;

enum AIAgent: string
{
    case Claude = 'claude';
    case Aider = 'aider';
    case Codex = 'codex';

    public function binary(): string
    {
        return $this->value;
    }

    /**
     * Build the full command array for a code-editing prompt.
     *
     * When $promptFile is set, the tool reads from the file instead of an
     * inline argument (avoids OS arg length limits on large prompts).
     *
     * @return list<string>
     */
    public function buildCommand(string $prompt, ?string $promptFile = null): array
    {
        return match ($this) {
            self::Claude => $this->claudeCommand($prompt, $promptFile),
            self::Aider => $this->aiderCommand($prompt, $promptFile),
            self::Codex => $this->codexCommand($prompt, $promptFile),
        };
    }

    /**
     * Build command for a one-shot prompt with no file editing.
     *
     * @return list<string>
     */
    public function buildSimpleCommand(string $prompt): array
    {
        return match ($this) {
            self::Claude => ['claude', '-p', $prompt, '--allowedTools', '', '--output-format', 'text'],
            self::Aider => ['aider', '--message', $prompt, '--no-auto-commits', '--no-git'],
            self::Codex => ['codex', '-q', $prompt, '--approval-mode', 'suggest'],
        };
    }

    public function timeout(): float
    {
        return 600.0;
    }

    public function installInstructions(): string
    {
        return match ($this) {
            self::Claude => 'Install Claude Code: npm install -g @anthropic-ai/claude-code'."\n"
                          .'Then run `claude` once to authenticate.',
            self::Aider => 'Install aider: pip install aider-chat'."\n"
                          .'Set ANTHROPIC_API_KEY or OPENAI_API_KEY in your environment.',
            self::Codex => 'Install Codex CLI: npm install -g @openai/codex'."\n"
                          .'Set OPENAI_API_KEY in your environment.',
        };
    }

    /**
     * @return list<string>
     */
    private function claudeCommand(string $prompt, ?string $promptFile): array
    {
        $cmd = ['claude', '-p'];

        if ($promptFile !== null) {
            $cmd[] = '--input-file';
            $cmd[] = $promptFile;
        } else {
            $cmd[] = $prompt;
        }

        return [...$cmd, '--allowedTools', 'edit,write,bash', '--output-format', 'text'];
    }

    /**
     * @return list<string>
     */
    private function aiderCommand(string $prompt, ?string $promptFile): array
    {
        $cmd = ['aider'];

        if ($promptFile !== null) {
            $cmd[] = '--message-file';
            $cmd[] = $promptFile;
        } else {
            $cmd[] = '--message';
            $cmd[] = $prompt;
        }

        return [...$cmd, '--yes-always', '--no-git'];
    }

    /**
     * @return list<string>
     */
    private function codexCommand(string $prompt, ?string $promptFile): array
    {
        $cmd = ['codex', '-q'];

        if ($promptFile !== null) {
            $cmd[] = '--input-file';
            $cmd[] = $promptFile;
        } else {
            $cmd[] = $prompt;
        }

        return [...$cmd, '--approval-mode', 'full-auto'];
    }
}
