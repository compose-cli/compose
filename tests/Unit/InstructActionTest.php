<?php

declare(strict_types=1);

use Compose\Actions\AI\InstructAction;
use Compose\Enums\AIAgent;
use Compose\Enums\InstructOperation;
use Compose\Execution\ActionResult;
use Compose\Execution\ProcessExecutor;
use Compose\Payloads\InstructPayload;

afterEach(function (): void {
    ProcessExecutor::reset();
});

describe('InstructAction', function (): void {
    function makePayload(
        string $description = 'Build a widget',
        array $creating = [],
        array $modifying = [],
    ): InstructPayload {
        return new InstructPayload(
            description: $description,
            creating: $creating,
            modifying: $modifying,
            using: [],
            like: [],
            rules: [],
            context: [],
            testing: [],
        );
    }

    // -------------------------------------------------------------------
    // type
    // -------------------------------------------------------------------

    it('returns the Instruct operation type', function (): void {
        $action = new InstructAction(makePayload());

        expect($action)->toBeOperation(InstructOperation::Instruct);
    });

    // -------------------------------------------------------------------
    // describe
    // -------------------------------------------------------------------

    describe('describe', function (): void {
        it('includes the description', function (): void {
            $action = new InstructAction(makePayload('Create the dashboard'));

            expect($action->describe())->toContain('instruct: Create the dashboard');
        });

        it('includes file count hints for creating', function (): void {
            $action = new InstructAction(makePayload(creating: ['a.php', 'b.php']));

            expect($action->describe())->toContain('2 to create');
        });

        it('includes file count hints for modifying', function (): void {
            $action = new InstructAction(makePayload(modifying: ['a.php']));

            expect($action->describe())->toContain('1 to modify');
        });

        it('combines creating and modifying hints', function (): void {
            $action = new InstructAction(makePayload(creating: ['a.php'], modifying: ['b.php']));

            expect($action->describe())->toContain('1 to create, 1 to modify');
        });

        it('omits hints when no files specified', function (): void {
            $action = new InstructAction(makePayload());
            $desc = $action->describe();

            expect($desc)->toBe('instruct: Build a widget');
            expect($desc)->not->toContain('(');
        });
    });

    // -------------------------------------------------------------------
    // execution
    // -------------------------------------------------------------------

    describe('execution', function (): void {
        it('returns failure when AI binary is not available', function (): void {
            ProcessExecutor::fake([
                'claude --version' => ActionResult::failure(errorOutput: 'not found'),
            ]);

            $action = (new InstructAction(makePayload()))
                ->withContext(context(workingDirectory: $this->tempPath))
                ->withExecutor(new ProcessExecutor);

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeFalse();
            expect($result->errorOutput)->toContain('not available');
            expect($result->errorOutput)->toContain('Install');
        });

        it('executes the AI and returns success', function (): void {
            ProcessExecutor::fake([
                'claude --version' => ActionResult::success(output: 'claude 1.0'),
                '*' => ActionResult::success(output: 'Done!'),
            ]);

            // Create a git repo so stash create works
            $this->createFile('.gitkeep', '');

            $action = (new InstructAction(makePayload()))
                ->withContext(context(workingDirectory: $this->tempPath))
                ->withExecutor(new ProcessExecutor);

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeTrue();
        });

        it('returns failure when AI CLI fails', function (): void {
            ProcessExecutor::fake([
                'claude --version' => ActionResult::success(output: 'claude 1.0'),
                'git stash create' => ActionResult::success(output: ''),
                'git ls-files*' => ActionResult::success(output: ''),
                '*' => ActionResult::failure(exitCode: 1, errorOutput: 'API error'),
            ]);

            $action = (new InstructAction(makePayload()))
                ->withContext(context(workingDirectory: $this->tempPath))
                ->withExecutor(new ProcessExecutor);

            $result = $action->execute(context(workingDirectory: $this->tempPath));

            expect($result->successful)->toBeFalse();
            expect($result->errorOutput)->toContain('API error');
        });
    });

    // -------------------------------------------------------------------
    // preflight
    // -------------------------------------------------------------------

    describe('preflight', function (): void {
        it('returns a PendingCommand for the AI binary', function (): void {
            $action = (new InstructAction(makePayload()))
                ->withContext(context());

            $preflight = $action->preflight();

            expect($preflight)->not->toBeNull();
            expect($preflight->toString())->toContain('claude');
            expect($preflight->toString())->toContain('--version');
        });

        it('uses the correct binary for each agent', function (): void {
            $action = (new InstructAction(makePayload()))
                ->withContext(context(aiAgent: AIAgent::Aider));

            $preflight = $action->preflight();

            expect($preflight->toString())->toContain('aider');
        });
    });

    // -------------------------------------------------------------------
    // rollback
    // -------------------------------------------------------------------

    describe('rollback', function (): void {
        it('can be rolled back', function (): void {
            $action = new InstructAction(makePayload());

            expect($action->canRollbackDirect())->toBeTrue();
        });
    });
});
