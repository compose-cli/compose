<?php

declare(strict_types=1);

namespace Compose\Actions\Modify;

use Compose\Actions\Action;
use Compose\Enums\ModifyOperation;
use Compose\Execution\ActionResult;
use Compose\Payloads\ModifyOperationPayload;
use Compose\RecipeContext;
use Compose\Support\JsonFile\JsonManipulator;
use Compose\Support\PhpFile\PhpFileEditor;
use Compose\Support\TextFile\TextManipulator;
use RuntimeException;
use Throwable;

class ModifyAction extends Action
{
    private ?string $originalContents = null;

    private bool $fileExisted = false;

    /**
     * @param  list<ModifyOperationPayload>  $operations
     */
    public function __construct(
        public readonly string $path,
        public readonly array $operations,
    ) {}

    #[\Override]
    public function type(): ModifyOperation
    {
        return ModifyOperation::Modify;
    }

    #[\Override]
    public function execute(RecipeContext $context): ActionResult
    {
        $fullPath = $this->resolvePath($this->path);

        if (! file_exists($fullPath)) {
            return ActionResult::failure(
                errorOutput: "File not found: {$this->path}",
                command: $this->descriptionArray(),
            );
        }

        $this->fileExisted = true;
        $this->originalContents = file_get_contents($fullPath) ?: '';

        $phpOps = $this->filterByCategory('php');
        $textOps = $this->filterByCategory('text');
        $jsonOps = $this->filterByCategory('json');

        $extension = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        if ($phpOps !== [] && $extension !== 'php') {
            return ActionResult::failure(
                errorOutput: "PHP class operations can only be applied to .php files: {$this->path}",
                command: $this->descriptionArray(),
            );
        }

        if ($jsonOps !== [] && $extension !== 'json') {
            return ActionResult::failure(
                errorOutput: "JSON operations can only be applied to .json files: {$this->path}",
                command: $this->descriptionArray(),
            );
        }

        try {
            $contents = $this->originalContents;

            if ($phpOps !== []) {
                $contents = $this->applyPhpOperations($contents, $phpOps);
            }

            if ($textOps !== []) {
                $contents = $this->applyTextOperations($contents, $textOps);
            }

            if ($jsonOps !== []) {
                $contents = $this->applyJsonOperations($contents, $jsonOps);
            }

            if (file_put_contents($fullPath, $contents) === false) {
                return ActionResult::failure(
                    errorOutput: "Failed to write file: {$this->path}",
                    command: $this->descriptionArray(),
                );
            }

            $opCount = count($this->operations);

            return ActionResult::success(
                command: $this->descriptionArray(),
                output: "Modified {$this->path} ({$opCount} operation".($opCount !== 1 ? 's' : '').')',
            );
        } catch (Throwable $e) {
            return ActionResult::failure(
                errorOutput: "Failed to modify {$this->path}: {$e->getMessage()}",
                command: $this->descriptionArray(),
            );
        }
    }

    #[\Override]
    public function describe(): string
    {
        $opCount = count($this->operations);

        return "modify {$this->path} ({$opCount} operation".($opCount !== 1 ? 's' : '').')';
    }

    #[\Override]
    public function canRollbackDirect(): bool
    {
        return true;
    }

    #[\Override]
    public function rollbackDirect(RecipeContext $context): ActionResult
    {
        $fullPath = $this->resolvePath($this->path);

        if (! $this->fileExisted) {
            return ActionResult::success(
                command: ['rollback:modify', $this->path],
                output: "Nothing to restore: {$this->path}",
            );
        }

        if ($this->originalContents !== null) {
            file_put_contents($fullPath, $this->originalContents);
        }

        return ActionResult::success(
            command: ['rollback:modify', $this->path],
            output: "Restored: {$this->path}",
        );
    }

    /**
     * @param  list<ModifyOperationPayload>  $ops
     */
    private function applyPhpOperations(string $contents, array $ops): string
    {
        $editor = PhpFileEditor::fromCode($contents);
        $manipulator = $editor->manipulator();

        foreach ($ops as $op) {
            match ($op->type) {
                'add_trait' => $manipulator->addTrait($op->arguments['trait']),
                'remove_trait' => $manipulator->removeTrait($op->arguments['trait']),
                'add_interface' => $manipulator->addInterface($op->arguments['interface']),
                'add_import' => $manipulator->addImport($op->arguments['class']),
                'remove_import' => $manipulator->removeImport($op->arguments['class']),
                'add_method' => $manipulator->addMethod(
                    $op->arguments['name'],
                    $op->arguments['body'],
                    $op->arguments['visibility'] ?? 'public',
                    $op->arguments['returnType'] ?? null,
                ),
                'add_property' => $manipulator->addProperty(
                    $op->arguments['name'],
                    $op->arguments['default'] ?? null,
                    $op->arguments['visibility'] ?? 'public',
                    $op->arguments['type'] ?? null,
                ),
                'add_constant' => $manipulator->addConstant(
                    $op->arguments['name'],
                    $op->arguments['value'],
                    $op->arguments['visibility'] ?? 'public',
                ),
                'add_to_array' => $manipulator->addToArray($op->arguments['property'], $op->arguments['values']),
                'add_to_method' => $manipulator->addToMethod($op->arguments['method'], $op->arguments['code']),
                'remove_method' => $manipulator->removeMethod($op->arguments['name']),
                default => throw new RuntimeException("Unknown PHP operation: {$op->type}"),
            };
        }

        return $editor->render();
    }

    /**
     * @param  list<ModifyOperationPayload>  $ops
     */
    private function applyTextOperations(string $contents, array $ops): string
    {
        foreach ($ops as $op) {
            $contents = match ($op->type) {
                'replace' => TextManipulator::replace($contents, $op->arguments['search'], $op->arguments['replace']),
                'replace_regex' => TextManipulator::replaceRegex($contents, $op->arguments['pattern'], $op->arguments['replace']),
                'prepend' => TextManipulator::prepend($contents, $op->arguments['contents']),
                'append' => TextManipulator::append($contents, $op->arguments['contents']),
                'insert_after' => TextManipulator::insertAfter($contents, $op->arguments['marker'], $op->arguments['contents']),
                'insert_before' => TextManipulator::insertBefore($contents, $op->arguments['marker'], $op->arguments['contents']),
                default => throw new RuntimeException("Unknown text operation: {$op->type}"),
            };
        }

        return $contents;
    }

    /**
     * @param  list<ModifyOperationPayload>  $ops
     */
    private function applyJsonOperations(string $contents, array $ops): string
    {
        $manipulator = new JsonManipulator($contents);

        foreach ($ops as $op) {
            match ($op->type) {
                'json_set' => $manipulator->set($op->arguments['key'], $op->arguments['value']),
                'json_merge' => $manipulator->merge($op->arguments['key'], $op->arguments['values']),
                'json_remove' => $manipulator->remove($op->arguments['key']),
                'json_push' => $manipulator->push($op->arguments['key'], $op->arguments['value']),
                default => throw new RuntimeException("Unknown JSON operation: {$op->type}"),
            };
        }

        return $manipulator->toString();
    }

    /**
     * @return list<ModifyOperationPayload>
     */
    private function filterByCategory(string $category): array
    {
        return array_values(array_filter($this->operations, fn (ModifyOperationPayload $op) => match ($category) {
            'php' => in_array($op->type, [
                'add_trait', 'remove_trait', 'add_interface', 'add_import', 'remove_import',
                'add_method', 'add_property', 'add_constant', 'add_to_array', 'add_to_method',
                'remove_method',
            ]),
            'text' => in_array($op->type, [
                'replace', 'replace_regex', 'prepend', 'append', 'insert_after', 'insert_before',
            ]),
            'json' => str_starts_with($op->type, 'json_'),
            default => false,
        }));
    }

    /**
     * @return string[]
     */
    private function descriptionArray(): array
    {
        return ['modify', $this->path];
    }
}
