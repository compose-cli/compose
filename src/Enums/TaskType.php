<?php

namespace Compose\Enums;

enum TaskType: string
{
    case NewProject = 'new-project';
    case NewFeature = 'new-feature';
    case Refactoring = 'refactoring';
}
