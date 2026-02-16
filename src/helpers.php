<?php

use Compose\Compose;
use Compose\Enums\TaskType;

if (! function_exists('compose')) {
    function compose(?string $name = null, TaskType|string $type = TaskType::NewProject): Compose
    {
        return new Compose($name, $type);
    }
}

if (! function_exists('slugify')) {
    function slugify(string $value): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9\s-]/', '', $value);
        $slug = preg_replace('/[\s-]+/', '-', trim((string) $slug));

        return strtolower((string) $slug);
    }
}
