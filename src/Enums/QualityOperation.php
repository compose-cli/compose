<?php

declare(strict_types=1);

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum QualityOperation: string implements Operation
{
    case Format = 'format';
    case Refactor = 'refactor';
}
