<?php

declare(strict_types=1);

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum FileOperation: string implements Operation
{
    case Create = 'create';
    case Read = 'read';
    case Delete = 'delete';
    case Copy = 'copy';
    case Append = 'append';
    case Sink = 'sink';
}
