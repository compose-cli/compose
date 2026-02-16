<?php

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum GitOperation: string implements Operation
{
    case Clone = 'clone';
    case Init = 'init';
    case Add = 'add';
    case Commit = 'commit';
}
