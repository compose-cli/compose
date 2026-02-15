<?php

use Compose\Compose;

if (! function_exists('compose')) {
    function compose(?string $name = null): Compose
    {
        return new Compose($name);
    }
}