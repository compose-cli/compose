<?php

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum FileOperation: string implements Operation
{
    // CRUD operations
    case Create = 'create';
    case Read = 'read';
    case Modify = 'modify';
    case Delete = 'delete';

    // Other operations
    case Copy = 'copy';
    case Append = 'append';
    case Sink = 'sink';
}
