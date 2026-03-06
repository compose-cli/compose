<?php

declare(strict_types=1);

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum VerifyOperation: string implements Operation
{
    case Verify = 'verify';
    case Test = 'test';
}
