<?php

declare(strict_types=1);

namespace Compose\Payloads;

class ModifyOperationPayload
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public readonly string $type,
        public readonly array $arguments = [],
    ) {}
}
