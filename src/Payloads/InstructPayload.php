<?php

declare(strict_types=1);

namespace Compose\Payloads;

class InstructPayload
{
    public function __construct(
        public readonly string $description,
        /** @var list<string> */
        public readonly array $creating,
        /** @var list<string> */
        public readonly array $modifying,
        /** @var list<array{path: string, include: bool}> */
        public readonly array $using,
        /** @var list<string> */
        public readonly array $like,
        /** @var list<string> */
        public readonly array $rules,
        /** @var array<string, mixed> */
        public readonly array $context,
        /** @var list<string> */
        public readonly array $testing,
        public readonly bool $bake = false,
    ) {}

    /**
     * Deterministic hash of this payload's definition.
     *
     * Used as the bake cache key (future). Any change to the instruct
     * definition produces a different hash. The bake flag is excluded
     * since it's an execution option, not a definition field.
     */
    public function hash(): string
    {
        $data = [
            $this->description,
            $this->creating,
            $this->modifying,
            $this->using,
            $this->like,
            $this->rules,
            $this->context,
            $this->testing,
        ];

        return hash('xxh128', serialize($data));
    }

    /**
     * Paths from using() that should have contents inlined in the prompt.
     *
     * @return list<string>
     */
    public function includedFiles(): array
    {
        return array_values(array_map(
            fn (array $entry) => $entry['path'],
            array_filter($this->using, fn (array $entry) => $entry['include']),
        ));
    }

    /**
     * Paths from using() that are hints only (not inlined).
     *
     * @return list<string>
     */
    public function hintedFiles(): array
    {
        return array_values(array_map(
            fn (array $entry) => $entry['path'],
            array_filter($this->using, fn (array $entry) => ! $entry['include']),
        ));
    }
}
