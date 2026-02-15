<?php

namespace Compose\Enums;

use Compose\Contracts\AI;

enum Anthropic: string implements AI
{
    case ClaudeOpus45 = 'claude-opus-4-5';

    public function provider(): string
    {
        return 'anthropic';
    }
}