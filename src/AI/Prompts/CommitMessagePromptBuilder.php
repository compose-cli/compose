<?php

declare(strict_types=1);

namespace Compose\AI\Prompts;

class CommitMessagePromptBuilder
{
    public function build(string $diff, string $stepName): string
    {
        return <<<PROMPT
            Generate a git commit message for the following changes made during the "{$stepName}" step.

            Requirements:
            - Use conventional commits format (e.g. feat:, fix:, chore:, refactor:)
            - Subject line max 72 characters
            - Output ONLY the commit message — no explanation, no markdown fences, no preamble
            - If the diff is large, focus on the most significant changes for the subject line

            Diff:
            ```
            {$diff}
            ```
            PROMPT;
    }
}
