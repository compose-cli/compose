<?php

declare(strict_types=1);

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum ConfigOperation: string implements Operation
{
    case Config = 'config';
}
