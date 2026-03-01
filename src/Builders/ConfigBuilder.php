<?php

declare(strict_types=1);

namespace Compose\Builders;

class ConfigBuilder
{
    /** @var list<array{type: string, ...}> */
    protected array $operations = [];

    /**
     * Set a config value at the given key path.
     *
     * Supports dot-notation for nested keys: 'models.role' sets ['models']['role'].
     */
    public function set(string $key, mixed $value): static
    {
        $this->operations[] = ['type' => 'set', 'key' => $key, 'value' => $value];

        return $this;
    }

    /**
     * Remove a config key.
     *
     * Supports dot-notation for nested keys.
     */
    public function remove(string $key): static
    {
        $this->operations[] = ['type' => 'remove', 'key' => $key];

        return $this;
    }

    /**
     * Merge values into an existing array key.
     *
     * Values already present in the array are skipped.
     *
     * @param  array<mixed>  $values
     */
    public function merge(string $key, array $values): static
    {
        $this->operations[] = ['type' => 'merge', 'key' => $key, 'value' => $values];

        return $this;
    }

    /**
     * Push a single value onto an array key.
     */
    public function push(string $key, mixed $value): static
    {
        $this->operations[] = ['type' => 'push', 'key' => $key, 'value' => $value];

        return $this;
    }

    /**
     * Comment out a config key.
     *
     * The key-value pair is preserved as a PHP comment in the output.
     */
    public function comment(string $key): static
    {
        $this->operations[] = ['type' => 'comment', 'key' => $key];

        return $this;
    }

    /**
     * @return list<array{type: string, ...}>
     */
    public function operations(): array
    {
        return $this->operations;
    }
}
