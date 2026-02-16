<?php

namespace Compose\Execution;

use Compose\Execution\Concerns\HasFakeProcessExecutor;
use Symfony\Component\Process\Process;

class ProcessExecutor
{
    use HasFakeProcessExecutor;

    /**
     * Execute a command and return the result.
     *
     * @param  string[]  $command
     */
    public function execute(array $command, ?string $cwd = null, ?float $timeout = null): ActionResult
    {
        if (static::$fake !== null) {
            return static::$fake->handle($command, $cwd, $timeout);
        }

        $startTime = microtime(true);

        $process = new Process($command, $cwd);
        $process->setTimeout($timeout);
        $process->run();

        $duration = microtime(true) - $startTime;

        return new ActionResult(
            command: $command,
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
            successful: $process->isSuccessful(),
            duration: $duration,
        );
    }
}
