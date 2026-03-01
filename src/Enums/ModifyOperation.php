<?php

declare(strict_types=1);

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum ModifyOperation: string implements Operation
{
    case Modify = 'modify';
}
