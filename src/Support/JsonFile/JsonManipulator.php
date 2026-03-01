<?php

declare(strict_types=1);

namespace Compose\Support\JsonFile;

use RuntimeException;

class JsonManipulator
{
    /** @var array<string, mixed> */
    private array $data;

    public function __construct(string $json)
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid JSON: '.json_last_error_msg());
        }

        $this->data = $decoded;
    }

    /**
     * Set a value using dot-notation keys.
     */
    public function set(string $key, mixed $value): static
    {
        $keys = explode('.', $key);
        $data = &$this->data;

        foreach ($keys as $segment) {
            if (! is_array($data)) {
                $data = [];
            }

            $data = &$data[$segment];
        }

        $data = $value;
        unset($data);

        return $this;
    }

    /**
     * Merge values into an existing array at the given dot-notation key.
     *
     * @param  array<mixed>  $values
     */
    public function merge(string $key, array $values): static
    {
        $current = $this->get($key);

        if (! is_array($current)) {
            $current = [];
        }

        $this->set($key, array_merge($current, $values));

        return $this;
    }

    /**
     * Remove a key using dot-notation.
     */
    public function remove(string $key): static
    {
        $keys = explode('.', $key);
        $last = array_pop($keys);
        $data = &$this->data;

        foreach ($keys as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                return $this;
            }

            $data = &$data[$segment];
        }

        unset($data[$last]);

        return $this;
    }

    /**
     * Push a value onto an array at the given dot-notation key.
     */
    public function push(string $key, mixed $value): static
    {
        $current = $this->get($key);

        if (! is_array($current)) {
            $current = [];
        }

        $current[] = $value;
        $this->set($key, $current);

        return $this;
    }

    public function toString(): string
    {
        return json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    private function get(string $key): mixed
    {
        $keys = explode('.', $key);
        $data = $this->data;

        foreach ($keys as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                return null;
            }

            $data = $data[$segment];
        }

        return $data;
    }
}
