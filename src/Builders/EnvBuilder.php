<?php

declare(strict_types=1);

namespace Compose\Builders;

use Closure;
use Compose\Support\TextFile\EnvFileParser;

class EnvBuilder
{
    /** @var list<array{type: string, ...}> */
    protected array $operations = [];

    protected ?string $afterKey = null;

    public function set(string $key, string $value): static
    {
        $this->operations[] = ['type' => 'set', 'key' => $key, 'value' => $value, 'after' => $this->afterKey];
        $this->afterKey = null;

        return $this;
    }

    public function remove(string $key): static
    {
        $this->operations[] = ['type' => 'remove', 'key' => $key];

        return $this;
    }

    public function comment(string $key): static
    {
        $this->operations[] = ['type' => 'comment', 'key' => $key];

        return $this;
    }

    public function uncomment(string $key): static
    {
        $this->operations[] = ['type' => 'uncomment', 'key' => $key];

        return $this;
    }

    /**
     * Set multiple key-value pairs at once.
     *
     * @param  array<string, string>  $values
     */
    public function merge(array $values): static
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * Set the insertion point for the next set() or section() call.
     */
    public function after(string $key): static
    {
        $this->afterKey = $key;

        return $this;
    }

    /**
     * Add a section with a comment header and key-value pairs.
     *
     * @param  array<string, string>  $values
     */
    public function section(string $header, array $values): static
    {
        $this->operations[] = ['type' => 'section', 'header' => $header, 'values' => $values, 'after' => $this->afterKey];
        $this->afterKey = null;

        return $this;
    }

    /**
     * Conditionally apply operations at execution time.
     *
     * Three forms:
     *   when('KEY', 'value', fn(EnvBuilder) => ...)  — key equals value
     *   when('KEY', fn(EnvBuilder) => ...)            — key exists
     *   when(fn(EnvFileParser) => bool, fn(EnvBuilder) => ...)  — custom condition
     */
    public function when(string|Closure $key, string|Closure $valueOrCallback, ?Closure $callback = null): static
    {
        if (is_string($key) && is_string($valueOrCallback)) {
            $conditionKey = $key;
            $conditionValue = $valueOrCallback;
            $this->operations[] = [
                'type' => 'when',
                'condition' => static fn (EnvFileParser $p): bool => $p->get($conditionKey) === $conditionValue,
                'callback' => $callback,
            ];
        } elseif (is_string($key) && $valueOrCallback instanceof Closure) {
            $conditionKey = $key;
            $this->operations[] = [
                'type' => 'when',
                'condition' => static fn (EnvFileParser $p): bool => $p->has($conditionKey),
                'callback' => $valueOrCallback,
            ];
        } else {
            $this->operations[] = [
                'type' => 'when',
                'condition' => $key,
                'callback' => $valueOrCallback,
            ];
        }

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
