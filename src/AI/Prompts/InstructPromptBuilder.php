<?php

declare(strict_types=1);

namespace Compose\AI\Prompts;

use Compose\Payloads\InstructPayload;
use Compose\RecipeContext;

class InstructPromptBuilder
{
    public function build(InstructPayload $payload, RecipeContext $context): string
    {
        $sections = [];

        $sections[] = "## Task\n{$payload->description}";

        if ($scope = $this->buildScope($payload)) {
            $sections[] = $scope;
        }

        if ($contextFiles = $this->buildContextFiles($payload, $context)) {
            $sections[] = $contextFiles;
        }

        if ($styleRefs = $this->buildStyleReferences($payload, $context)) {
            $sections[] = $styleRefs;
        }

        if ($payload->testing !== []) {
            $list = implode("\n", array_map(fn (string $p) => "- {$p}", $payload->testing));
            $sections[] = "## Tests\nCreate or update these test files:\n{$list}";
        }

        if ($payload->rules !== []) {
            $list = implode("\n", array_map(fn (string $r) => "- {$r}", $payload->rules));
            $sections[] = "## Rules\n{$list}";
        }

        if ($payload->context !== []) {
            $pairs = [];
            foreach ($payload->context as $key => $value) {
                $pairs[] = "{$key}: {$value}";
            }
            $sections[] = "## Project Context\n".implode("\n", $pairs);
        }

        return implode("\n\n", $sections)."\n";
    }

    private function buildScope(InstructPayload $payload): ?string
    {
        if ($payload->creating === [] && $payload->modifying === []) {
            return null;
        }

        $lines = ["## Scope\nFocus on the files listed below. Do not modify other files unless strictly necessary to complete the task."];

        if ($payload->creating !== []) {
            $list = implode("\n", array_map(fn (string $p) => "- {$p}", $payload->creating));
            $lines[] = "Files to create:\n{$list}";
        }

        if ($payload->modifying !== []) {
            $list = implode("\n", array_map(fn (string $p) => "- {$p}", $payload->modifying));
            $lines[] = "Files to modify:\n{$list}";
        }

        return implode("\n\n", $lines);
    }

    private function buildContextFiles(InstructPayload $payload, RecipeContext $context): ?string
    {
        $hinted = $payload->hintedFiles();
        $included = $payload->includedFiles();

        if ($hinted === [] && $included === []) {
            return null;
        }

        $lines = [];

        if ($hinted !== []) {
            $list = implode("\n", array_map(fn (string $p) => "- {$p}", $hinted));
            $lines[] = "## Context Files\nThese files are relevant — read them for context:\n{$list}";
        }

        foreach ($included as $path) {
            $contents = $this->readFile($path, $context);
            $lang = $this->languageForExtension($path);

            if ($contents !== null) {
                $label = $hinted === [] && count($included) === 1
                    ? '## Context Files'
                    : "### {$path} (inlined)";

                if ($hinted !== [] || $included[0] !== $path) {
                    $label = "### {$path} (inlined)";
                }

                $lines[] = "{$label}\n```{$lang}\n{$contents}\n```";
            } else {
                $lines[] = "### {$path}\n(file not found)";
            }
        }

        return implode("\n\n", $lines);
    }

    private function buildStyleReferences(InstructPayload $payload, RecipeContext $context): ?string
    {
        if ($payload->like === []) {
            return null;
        }

        $lines = ["## Style References\nMatch the patterns and conventions in these files:"];

        foreach ($payload->like as $path) {
            $contents = $this->readFile($path, $context);
            $lang = $this->languageForExtension($path);

            if ($contents !== null) {
                $lines[] = "### {$path}\n```{$lang}\n{$contents}\n```";
            } else {
                $lines[] = "### {$path}\n(file not found)";
            }
        }

        return implode("\n\n", $lines);
    }

    private function readFile(string $path, RecipeContext $context): ?string
    {
        $cwd = $context->workingDirectory;

        $fullPath = $cwd !== null
            ? rtrim($cwd, '/\\').DIRECTORY_SEPARATOR.$path
            : $path;

        if (! file_exists($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath) ?: null;
    }

    private function languageForExtension(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'php' => str_ends_with($path, '.blade.php') ? 'blade' : 'php',
            'json' => 'json',
            'yaml', 'yml' => 'yaml',
            'js' => 'javascript',
            'ts' => 'typescript',
            'vue' => 'vue',
            'css' => 'css',
            'scss', 'sass' => 'scss',
            'html' => 'html',
            'xml' => 'xml',
            'md' => 'markdown',
            'sql' => 'sql',
            'sh', 'bash' => 'bash',
            default => $ext,
        };
    }
}
