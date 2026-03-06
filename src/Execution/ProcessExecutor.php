<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Execution\Concerns\HasFakeProcessExecutor;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ProcessExecutor
{
    use HasFakeProcessExecutor;

    public const float DEFAULT_TIMEOUT = 300.0;

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
        $process->setTimeout($timeout ?? self::DEFAULT_TIMEOUT);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $duration = microtime(true) - $startTime;
            $effectiveTimeout = $timeout ?? self::DEFAULT_TIMEOUT;

            return new ActionResult(
                command: $command,
                exitCode: 124,
                output: $process->getOutput(),
                errorOutput: "Process timed out after {$effectiveTimeout}s: ".implode(' ', $command),
                successful: false,
                duration: $duration,
            );
        }

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
