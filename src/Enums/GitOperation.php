<?php

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum GitOperation: string implements Operation
{
    case Clone = 'clone';
}