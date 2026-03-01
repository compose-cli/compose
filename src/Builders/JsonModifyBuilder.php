<?php

declare(strict_types=1);

namespace Compose\Builders;

use Compose\Payloads\ModifyOperationPayload;

class JsonModifyBuilder
{
    /** @var list<ModifyOperationPayload> */
    protected array $operations = [];

    /**
     * Set a value at a dot-notation key.
     */
    public function set(string $key, mixed $value): static
    {
        $this->operations[] = new ModifyOperationPayload('json_set', ['key' => $key, 'value' => $value]);

        return $this;
    }

    /**
     * Merge values into an array at a dot-notation key.
     *
     * @param  array<mixed>  $values
     */
    public function merge(string $key, array $values): static
    {
        $this->operations[] = new ModifyOperationPayload('json_merge', ['key' => $key, 'values' => $values]);

        return $this;
    }

    /**
     * Remove a key using dot-notation.
     */
    public function remove(string $key): static
    {
        $this->operations[] = new ModifyOperationPayload('json_remove', ['key' => $key]);

        return $this;
    }

    /**
     * Push a value onto an array at a dot-notation key.
     */
    public function push(string $key, mixed $value): static
    {
        $this->operations[] = new ModifyOperationPayload('json_push', ['key' => $key, 'value' => $value]);

        return $this;
    }

    /**
     * @return list<ModifyOperationPayload>
     */
    public function operations(): array
    {
        return $this->operations;
    }
}
