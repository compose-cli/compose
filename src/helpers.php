<?php

use Compose\Compose;
use Compose\Enums\TaskType;

if (! function_exists('compose')) {
    function compose(?string $name = null, TaskType|string $type = TaskType::NewProject): Compose
    {
        return new Compose($name, $type);
    }
}