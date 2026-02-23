<?php

declare(strict_types=1);

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum ArtisanOperation: string implements Operation
{
    case Run = 'artisan:run';
    case Make = 'artisan:make';
}
