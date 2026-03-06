<?php

require __DIR__.'/vendor/autoload.php';

use Compose\Console\Commands\PlanCommand;
use Compose\Console\Commands\RunCommand;
use Symfony\Component\Console\Application;

$application = new Application(
    name: 'Compose CLI',
    version: '0.0.1',
);

$commands = [
    new RunCommand,
    new PlanCommand,
];

$application->addCommands($commands);
$application->setDefaultCommand('run');

$exitCode = $application->run();

exit($exitCode);
