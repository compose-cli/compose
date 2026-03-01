<?php

declare(strict_types=1);

namespace Compose\Enums;

enum TaskType: string
{
    case NewProject = 'new-project';
    case NewFeature = 'new-feature';
    case Refactoring = 'refactoring';
}
