<?php

require __DIR__.'/vendor/autoload.php';

use Compose\Console\Commands\ComposeCommand;
use Symfony\Component\Console\Application;

$application = new Application(
    name: 'Compose CLI',
    version: '0.0.1',
);

$commands = [
    new ComposeCommand,
];

$application->addCommands($commands);

$exitCode = $application->run();

exit($exitCode);
