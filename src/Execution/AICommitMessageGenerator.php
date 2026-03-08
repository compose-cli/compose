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
        private readonly ProcessExecutor $executor = new ProcessExecutor,
        private readonly CommitMessagePromptBuilder $promptBuilder = new CommitMessagePromptBuilder,
        private readonly DefaultCommitMessageGenerator $fallback = new DefaultCommitMessageGenerator,
    ) {}

    /**
     * @param  ActionResult[]  $actionResults
     */
    #[\Override]
    public function generate(Step $step, array $actionResults): string
    {
        $diff = $this->getStagedDiff();

        if ($diff === '') {
            return $this->fallback->generate($step, $actionResults);
        }

        $prompt = $this->promptBuilder->build($diff, $step->name);
        $result = $this->agent->prompt($prompt, $this->context);

        if (! $result->successful) {
            return $this->fallback->generate($step, $actionResults);
        }

        $message = $this->cleanResponse($result->output);

        if ($message === '') {
            return $this->fallback->generate($step, $actionResults);
        }

        return $message;
    }

    private function getStagedDiff(): string
    {
        $result = $this->executor->execute(
            [$this->context->gitBinary, 'diff', '--staged'],
            $this->context->workingDirectory,
            30.0,
        );

        return $result->successful ? trim($result->output) : '';
    }

    private function cleanResponse(string $output): string
    {
        $output = trim($output);

        $output = preg_replace('/^```\w*\n?/', '', $output);
        $output = preg_replace('/\n?```$/', '', (string) $output);
        $output = trim((string) $output, '`');

        return trim($output);
    }
}
