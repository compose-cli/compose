<?php

declare(strict_types=1);

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum InstructOperation: string implements Operation
{
    case Instruct = 'instruct';
}
