<?php

namespace Compose\Enums;

enum Node: string
{
    case Npm = 'npm';
    case Yarn = 'yarn';
    case Pnpm = 'pnpm';
    case Bun = 'bun';
}