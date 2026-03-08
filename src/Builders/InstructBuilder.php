<?php

declare(strict_types=1);

namespace Compose\Builders;

use Compose\Payloads\InstructPayload;

class InstructBuilder
{
    /** @var list<string> */
    protected array $creating = [];

    /** @var list<string> */
    protected array $modifying = [];

    /** @var list<array{path: string, include: bool}> */
    protected array $using = [];

    /** @var list<string> */
    protected array $like = [];

    /** @var list<string> */
    protected array $rules = [];

    /** @var array<string, mixed> */
    protected array $context = [];

    /** @var list<string> */
    protected array $testing = [];

    protected bool $bake = false;

    /**
     * Hint that the AI should create this file.
     */
    public function creating(string ...$paths): static
    {
        array_push($this->creating, ...$paths);

        return $this;
    }

    /**
     * Hint that the AI should modify this file.
     */
    public function modifying(string ...$paths): static
    {
        array_push($this->modifying, ...$paths);

        return $this;
    }

    /**
     * Mark files as relevant context for the AI.
     *
     * By default this is a hint: the file path is listed in the prompt so the
     * AI tool knows to read it, but the contents are NOT inlined.
     *
     * Pass include: true to read the file contents into the prompt directly.
     */
    public function using(string $path, bool $include = false): static
    {
        $this->using[] = ['path' => $path, 'include' => $include];

        return $this;
    }

    /**
     * Use these files as style/pattern references.
     * Contents are always inlined.
     */
    public function like(string ...$paths): static
    {
        array_push($this->like, ...$paths);

        return $this;
    }

    /**
     * Freeform rules the AI must follow.
     *
     * @param  list<string>  $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = array_merge($this->rules, $rules);

        return $this;
    }

    /**
     * Key-value context pairs for the prompt.
     *
     * @param  array<string, mixed>  $context
     */
    public function with(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Test files the AI should create or that should pass after the instruct.
     */
    public function testing(string ...$paths): static
    {
        array_push($this->testing, ...$paths);

        return $this;
    }

    /**
     * Enable git-based patch caching (not yet implemented).
     */
    public function bake(bool $bake = true): static
    {
        $this->bake = $bake;

        return $this;
    }

    /**
     * Compile to an immutable payload.
     */
    public function toPayload(string $description): InstructPayload
    {
        return new InstructPayload(
            description: $description,
            creating: $this->creating,
            modifying: $this->modifying,
            using: $this->using,
            like: $this->like,
            rules: $this->rules,
            context: $this->context,
            testing: $this->testing,
            bake: $this->bake,
        );
    }
}
