<?php

namespace Compose\Execution\Concerns;

use Compose\Execution\ActionResult;
use Compose\Execution\FakeProcessExecutor;

trait HasFakeProcessExecutor
{
    protected static ?FakeProcessExecutor $fake = null;

    /**
     * Create a fake executor for testing.
     *
     * @param  array<string, ActionResult>  $responses  Pattern => result mappings
     */
    public static function fake(array $responses = []): FakeProcessExecutor
    {
        return static::$fake = new FakeProcessExecutor($responses);
    }

    /**
     * Assert that a command was executed.
     *
     * @param  string[]  $command
     */
    public static function assertExecuted(array $command): void
    {
        static::getFake()->assertExecuted($command);
    }

    /**
     * Assert that a command was not executed.
     *
     * @param  string[]  $command
     */
    public static function assertNotExecuted(array $command): void
    {
        static::getFake()->assertNotExecuted($command);
    }

    /**
     * Assert that no commands were executed.
     */
    public static function assertNothingExecuted(): void
    {
        static::getFake()->assertNothingExecuted();
    }

    /**
     * Reset the fake.
     */
    public static function reset(): void
    {
        static::$fake = null;
    }

    private static function getFake(): FakeProcessExecutor
    {
        if (static::$fake === null) {
            throw new \RuntimeException('ProcessExecutor::fake() has not been called.');
        }

        return static::$fake;
    }
}
