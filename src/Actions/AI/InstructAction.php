<?php

declare(strict_types=1);

namespace Compose\Actions\AI;

use Compose\Actions\Action;
use Compose\Actions\PendingCommand;
use Compose\AI\CLIAgent;
use Compose\AI\Prompts\InstructPromptBuilder;
use Compose\Enums\InstructOperation;
use Compose\Execution\ActionResult;
use Compose\Payloads\InstructPayload;
use Compose\RecipeContext;

class InstructAction extends Action
{
    /** @var list<string> Files that existed before AI ran (for rollback via git checkout) */
    private array $preExisting = [];

    /** @var list<string> Files created by the AI (for rollback via unlink) */
    private array $created = [];

    /** Baseline snapshot token for change isolation */
    private string $baseline = '';

    public function __construct(
        public readonly InstructPayload $payload,
    ) {}

    #[\Override]
    public function type(): InstructOperation
    {
        return InstructOperation::Instruct;
    }

    #[\Override]
    public function execute(RecipeContext $context): ActionResult
    {
        $agent = new CLIAgent($this->executor(), $context->aiAgent);

        if (! $agent->isAvailable()) {
            return ActionResult::failure(
                errorOutput: "AI CLI tool '{$context->aiAgent->binary()}' is not available.\n"
                           .$agent->installInstructions(),
            );
        }

        $this->baseline = $this->gitStashCreate($context);
        $untrackedBefore = $this->getUntrackedFiles($context);

        $promptBuilder = new InstructPromptBuilder;
        $prompt = $promptBuilder->build($this->payload, $context);

        $result = $agent->execute($prompt, $context);

        if (! $result->successful) {
            return ActionResult::failure(
                exitCode: $result->exitCode,
                errorOutput: $result->errorOutput ?: $result->output,
                command: $result->command,
            );
        }

        $this->detectChanges($context, $untrackedBefore);

        $summary = $this->buildSummary();

        return ActionResult::success(
            output: $summary."\n\n".$result->output,
            command: $result->command,
        );
    }

    #[\Override]
    public function preflight(): ?PendingCommand
    {
        return new PendingCommand($this->context()->aiAgent->binary(), '--version');
    }

    #[\Override]
    public function describe(): string
    {
        $hints = [];

        if ($this->payload->creating !== []) {
            $hints[] = count($this->payload->creating).' to create';
        }

        if ($this->payload->modifying !== []) {
            $hints[] = count($this->payload->modifying).' to modify';
        }

        $suffix = $hints !== [] ? ' ('.implode(', ', $hints).')' : '';

        return "instruct: {$this->payload->description}{$suffix}";
    }

    #[\Override]
    public function canRollbackDirect(): bool
    {
        return true;
    }

    #[\Override]
    public function rollbackDirect(RecipeContext $context): ?ActionResult
    {
        $errors = [];

        foreach ($this->preExisting as $file) {
            $result = $this->executor()->execute(
                [$context->gitBinary, 'checkout', '--', $file],
                $context->workingDirectory,
            );

            if (! $result->successful) {
                $errors[] = "Failed to restore {$file}: {$result->errorOutput}";
            }
        }

        foreach ($this->created as $file) {
            $fullPath = $this->resolvePath($file);

            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        if ($errors !== []) {
            return ActionResult::failure(errorOutput: implode("\n", $errors));
        }

        $count = count($this->preExisting) + count($this->created);

        return ActionResult::success(output: "Rolled back {$count} file(s)");
    }

    public function installInstructions(): string
    {
        return $this->context()->aiAgent->installInstructions();
    }

    private function detectChanges(RecipeContext $context, array $untrackedBefore): void
    {
        $untrackedAfter = $this->getUntrackedFiles($context);
        $this->created = array_values(array_diff($untrackedAfter, $untrackedBefore));

        $this->preExisting = $this->getModifiedTrackedFiles($context);
    }

    /**
     * @return list<string>
     */
    private function getUntrackedFiles(RecipeContext $context): array
    {
        $result = $this->executor()->execute(
            [$context->gitBinary, 'ls-files', '--others', '--exclude-standard'],
            $context->workingDirectory,
            10.0,
        );

        if (! $result->successful || trim($result->output) === '') {
            return [];
        }

        return array_values(array_filter(
            explode("\n", trim($result->output)),
            fn (string $line) => $line !== '',
        ));
    }

    /**
     * @return list<string>
     */
    private function getModifiedTrackedFiles(RecipeContext $context): array
    {
        $args = [$context->gitBinary, 'diff', '--name-only'];

        if ($this->baseline !== '') {
            $args[] = $this->baseline;
        }

        $result = $this->executor()->execute(
            $args,
            $context->workingDirectory,
            10.0,
        );

        if (! $result->successful || trim($result->output) === '') {
            return [];
        }

        return array_values(array_filter(
            explode("\n", trim($result->output)),
            fn (string $line) => $line !== '',
        ));
    }

    private function gitStashCreate(RecipeContext $context): string
    {
        $result = $this->executor()->execute(
            [$context->gitBinary, 'stash', 'create'],
            $context->workingDirectory,
            10.0,
        );

        return $result->successful ? trim($result->output) : '';
    }

    private function buildSummary(): string
    {
        $parts = [];

        if ($this->created !== []) {
            $parts[] = 'created '.count($this->created).' file(s)';
        }

        if ($this->preExisting !== []) {
            $parts[] = 'modified '.count($this->preExisting).' file(s)';
        }

        if ($parts === []) {
            return 'AI completed with no detected file changes';
        }

        $files = array_merge(
            array_map(fn (string $f) => "  + {$f}", $this->created),
            array_map(fn (string $f) => "  ~ {$f}", $this->preExisting),
        );

        return 'AI '.implode(', ', $parts).":\n".implode("\n", $files);
    }
}
