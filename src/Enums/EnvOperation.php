<?php

declare(strict_types=1);

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum EnvOperation: string implements Operation
{
    case Env = 'env';
}
