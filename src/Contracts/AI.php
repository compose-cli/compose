<?php

namespace Compose\Contracts;

use BackedEnum;

interface AI extends BackedEnum
{
    public function provider(): string;
}