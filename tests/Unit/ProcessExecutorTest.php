<?php

use Compose\Execution\ActionResult;
use Compose\Execution\ProcessExecutor;

describe('ProcessExecutor', function (): void {

    afterEach(function (): void {
        ProcessExecutor::reset();
    });

    it('executes a real command', function (): void {
        $executor = new ProcessExecutor;
        $result = $executor->execute(['echo', 'hello']);

        expect($result->successful)->toBeTrue();
        expect($result->exitCode)->toBe(0);
        expect(trim($result->output))->toBe('hello');
    });

    it('returns failure for bad commands', function (): void {
        $executor = new ProcessExecutor;
        $result = $executor->execute(['php', '-r', 'exit(42);']);

        expect($result->successful)->toBeFalse();
        expect($result->exitCode)->toBe(42);
    });

    it('can be faked', function (): void {
        ProcessExecutor::fake();

        $executor = new ProcessExecutor;
        $result = $executor->execute(['composer', 'require', 'laravel/framework']);

        expect($result->successful)->toBeTrue();
        expect($result->command)->toBe(['composer', 'require', 'laravel/framework']);
    });

    it('can fake specific responses', function (): void {
        ProcessExecutor::fake([
            'composer *' => ActionResult::success(),
            'git clone *' => ActionResult::failure(128, 'repo not found'),
        ]);

        $executor = new ProcessExecutor;

        $composerResult = $executor->execute(['composer', 'require', 'laravel/framework']);
        expect($composerResult->successful)->toBeTrue();

        $gitResult = $executor->execute(['git', 'clone', 'https://example.com/repo.git']);
        expect($gitResult->successful)->toBeFalse();
        expect($gitResult->exitCode)->toBe(128);
    });

    it('asserts commands were executed', function (): void {
        ProcessExecutor::fake();

        $executor = new ProcessExecutor;
        $executor->execute(['composer', 'require', 'laravel/framework']);

        ProcessExecutor::assertExecuted(['composer', 'require', 'laravel/framework']);
    });

    it('asserts commands were not executed', function (): void {
        ProcessExecutor::fake();

        $executor = new ProcessExecutor;
        $executor->execute(['composer', 'require', 'laravel/framework']);

        ProcessExecutor::assertNotExecuted(['git', 'clone', '*']);
    });

    it('asserts nothing was executed', function (): void {
        ProcessExecutor::fake();

        ProcessExecutor::assertNothingExecuted();
    });

});
