<?php

declare(strict_types=1);

namespace Compose\Contracts;

use BackedEnum;

interface AI extends BackedEnum
{
    public function provider(): string;
}
