<?php

namespace Compose\Enums;

use Compose\Contracts\AI;

enum OpenAI: string implements AI
{
    case GPT4o = 'gpt-4o';
    case GPT4oMini = 'gpt-4o-mini';

    public function provider(): string
    {
        return 'openai';
    }
}
