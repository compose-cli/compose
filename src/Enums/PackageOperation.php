<?php

namespace Compose\Enums;

use Compose\Contracts\Operation;

enum PackageOperation: string implements Operation
{
    case Install = 'install';
    case InstallDev = 'install-dev';
    case Remove = 'remove';
    case RemoveDev = 'remove-dev';
    case AddScript = 'script';
    case RemoveScript = 'script-remove';
    case Run = 'run';
}