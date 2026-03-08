<?php

declare(strict_types=1);

namespace Compose\Builders;

use Closure;
use Compose\Payloads\ModifyOperationPayload;

class ModifyBuilder
{
    /** @var list<ModifyOperationPayload> */
    protected array $operations = [];

    // ── PHP Class Operations ──

    /**
     * Add a trait to the class. FQCN auto-resolves the import.
     */
    public function addTrait(string $trait): static
    {
        $this->operations[] = new ModifyOperationPayload('add_trait', ['trait' => $trait]);

        return $this;
    }

    public function removeTrait(string $trait): static
    {
        $this->operations[] = new ModifyOperationPayload('remove_trait', ['trait' => $trait]);

        return $this;
    }

    /**
     * Add an interface to the class. FQCN auto-resolves the import.
     */
    public function addInterface(string $interface): static
    {
        $this->operations[] = new ModifyOperationPayload('add_interface', ['interface' => $interface]);

        return $this;
    }

    /**
     * Add a use import to the file's namespace.
     */
    public function addImport(string $class): static
    {
        $this->operations[] = new ModifyOperationPayload('add_import', ['class' => $class]);

        return $this;
    }

    public function removeImport(string $class): static
    {
        $this->operations[] = new ModifyOperationPayload('remove_import', ['class' => $class]);

        return $this;
    }

    public function addMethod(
        string $name,
        string $body,
        string $visibility = 'public',
        ?string $returnType = null,
    ): static {
        $this->operations[] = new ModifyOperationPayload('add_method', [
            'name' => $name,
            'body' => $body,
            'visibility' => $visibility,
            'returnType' => $returnType,
        ]);

        return $this;
    }

    public function addProperty(
        string $name,
        mixed $default = null,
        string $visibility = 'public',
        ?string $type = null,
    ): static {
        $this->operations[] = new ModifyOperationPayload('add_property', [
            'name' => $name,
            'default' => $default,
            'visibility' => $visibility,
            'type' => $type,
        ]);

        return $this;
    }

    public function addConstant(
        string $name,
        mixed $value,
        string $visibility = 'public',
    ): static {
        $this->operations[] = new ModifyOperationPayload('add_constant', [
            'name' => $name,
            'value' => $value,
            'visibility' => $visibility,
        ]);

        return $this;
    }

    /**
     * Add values to an existing array property.
     *
     * @param  array<mixed>  $values
     */
    public function addToArray(string $property, array $values): static
    {
        $this->operations[] = new ModifyOperationPayload('add_to_array', [
            'property' => $property,
            'values' => $values,
        ]);

        return $this;
    }

    /**
     * Append code to an existing method's body.
     */
    public function addToMethod(string $method, string $code): static
    {
        $this->operations[] = new ModifyOperationPayload('add_to_method', [
            'method' => $method,
            'code' => $code,
        ]);

        return $this;
    }

    public function removeMethod(string $name): static
    {
        $this->operations[] = new ModifyOperationPayload('remove_method', ['name' => $name]);

        return $this;
    }

    // ── Text Operations ──

    public function replace(string $search, string $replace): static
    {
        $this->operations[] = new ModifyOperationPayload('replace', [
            'search' => $search,
            'replace' => $replace,
        ]);

        return $this;
    }

    public function replaceRegex(string $pattern, string $replace): static
    {
        $this->operations[] = new ModifyOperationPayload('replace_regex', [
            'pattern' => $pattern,
            'replace' => $replace,
        ]);

        return $this;
    }

    public function prepend(string $contents): static
    {
        $this->operations[] = new ModifyOperationPayload('prepend', ['contents' => $contents]);

        return $this;
    }

    public function append(string $contents): static
    {
        $this->operations[] = new ModifyOperationPayload('append', ['contents' => $contents]);

        return $this;
    }

    public function insertAfter(string $marker, string $contents): static
    {
        $this->operations[] = new ModifyOperationPayload('insert_after', [
            'marker' => $marker,
            'contents' => $contents,
        ]);

        return $this;
    }

    public function insertBefore(string $marker, string $contents): static
    {
        $this->operations[] = new ModifyOperationPayload('insert_before', [
            'marker' => $marker,
            'contents' => $contents,
        ]);

        return $this;
    }

    // ── JSON Operations ──

    /**
     * Delegate to JsonModifyBuilder for JSON file operations.
     *
     * @param  Closure(JsonModifyBuilder): void  $callback
     */
    public function json(Closure $callback): static
    {
        $builder = new JsonModifyBuilder;
        $callback($builder);

        $this->operations = array_merge($this->operations, $builder->operations());

        return $this;
    }

    /**
     * @return list<ModifyOperationPayload>
     *
     * @throws \LogicException When PHP class operations and JSON operations are mixed.
     */
    public function operations(): array
    {
        $hasPhpClass = false;
        $hasJson = false;

        foreach ($this->operations as $op) {
            if (str_starts_with($op->type, 'json_')) {
                $hasJson = true;
            } elseif (in_array($op->type, [
                'add_trait', 'remove_trait', 'add_interface',
                'add_import', 'remove_import', 'add_method',
                'add_property', 'add_constant', 'add_to_array',
                'add_to_method', 'remove_method',
            ], true)) {
                $hasPhpClass = true;
            }

            if ($hasPhpClass && $hasJson) {
                throw new \LogicException(
                    'Cannot mix PHP class operations and JSON operations in the same ModifyBuilder.',
                );
            }
        }

        return $this->operations;
    }
}
