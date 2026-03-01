<?php

declare(strict_types=1);

namespace Compose\Enums;

enum Node: string
{
    case Npm = 'npm';
    case Yarn = 'yarn';
    case Pnpm = 'pnpm';
    case Bun = 'bun';
}
