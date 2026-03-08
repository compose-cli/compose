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
     * Long prompts are written to a temp file to avoid OS arg length limits.
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

    public function installInstructions(): string
    {
        return $this->agent->installInstructions();
    }

    private function writePromptFile(string $prompt, RecipeContext $context): string
    {
        $dir = $context->workingDirectory ?? sys_get_temp_dir();
        $path = rtrim($dir, '/\\').DIRECTORY_SEPARATOR
              .'.compose-prompt-'.bin2hex(random_bytes(8)).'.md';

        file_put_contents($path, $prompt);

        return $path;
    }
}
