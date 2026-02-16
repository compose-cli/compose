<?php

namespace Compose\Execution;

use PHPUnit\Framework\Assert;

class FakeProcessExecutor
{
    /**
     * @var array<array{command: string[], cwd: ?string}>
     */
    protected array $executed = [];

    /**
     * @param  array<string, ActionResult>  $responses  Pattern => result mappings
     */
    public function __construct(
        protected array $responses = [],
    ) {}

    /**
     * Handle a fake command execution.
     *
     * @param  string[]  $command
     */
    public function handle(array $command, ?string $cwd = null, ?float $timeout = null): ActionResult
    {
        $this->executed[] = ['command' => $command, 'cwd' => $cwd];

        $commandString = implode(' ', $command);

        foreach ($this->responses as $pattern => $result) {
            if ($this->matchesPattern($commandString, $pattern)) {
                return new ActionResult(
                    command: $command,
                    exitCode: $result->exitCode,
                    output: $result->output,
                    errorOutput: $result->errorOutput,
                    successful: $result->successful,
                    duration: 0.0,
                    action: $result->action,
                );
            }
        }

        return ActionResult::success(command: $command);
    }

    /**
     * Assert that a command matching the given pattern was executed.
     *
     * @param  string[]  $command
     */
    public function assertExecuted(array $command): void
    {
        $pattern = implode(' ', $command);

        $found = false;

        foreach ($this->executed as $record) {
            $executed = implode(' ', $record['command']);

            if ($this->matchesPattern($executed, $pattern)) {
                $found = true;

                break;
            }
        }

        Assert::assertTrue($found, "Expected command [{$pattern}] was not executed.");
    }

    /**
     * Assert that a command matching the given pattern was not executed.
     *
     * @param  string[]  $command
     */
    public function assertNotExecuted(array $command): void
    {
        $pattern = implode(' ', $command);

        foreach ($this->executed as $record) {
            $executed = implode(' ', $record['command']);

            if ($this->matchesPattern($executed, $pattern)) {
                Assert::fail("Unexpected command [{$pattern}] was executed.");
            }
        }

        Assert::assertTrue(true);
    }

    /**
     * Assert that no commands were executed.
     */
    public function assertNothingExecuted(): void
    {
        Assert::assertEmpty($this->executed, 'Expected no commands to be executed, but '.count($this->executed).' were.');
    }

    /**
     * Get all executed commands.
     *
     * @return array<array{command: string[], cwd: ?string}>
     */
    public function executed(): array
    {
        return $this->executed;
    }

    /**
     * Match a command string against a pattern (supports * wildcards).
     */
    protected function matchesPattern(string $subject, string $pattern): bool
    {
        $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/';

        return (bool) preg_match($regex, $subject);
    }
}
