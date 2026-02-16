<?php

use Compose\Actions\Composer\ComposerInstall;
use Compose\Actions\Composer\ComposerRun;
use Compose\Execution\ProcessExecutor;
use Compose\Execution\RollbackManager;

describe('RollbackManager', function (): void {

    afterEach(function (): void {
        ProcessExecutor::reset();
    });

    it('tracks actions and rolls them back in reverse order', function (): void {
        $fake = ProcessExecutor::fake();
        $ctx = context();
        $executor = new ProcessExecutor;
        $rollback = new RollbackManager;

        $action1 = (new ComposerInstall(['laravel/framework']))->withContext($ctx);
        $action2 = (new ComposerInstall(['laravel/sanctum']))->withContext($ctx);

        $rollback->beginStep('test-step');
        $rollback->push($action1);
        $rollback->push($action2);

        $results = $rollback->rollbackCurrentStep($ctx, $executor);

        expect($results)->toHaveCount(2);

        $executed = $fake->executed();
        expect($executed[0]['command'])->toBe(['composer', 'remove', 'laravel/sanctum']);
        expect($executed[1]['command'])->toBe(['composer', 'remove', 'laravel/framework']);
    });

    it('skips non-rollbackable actions', function (): void {
        $fake = ProcessExecutor::fake();
        $ctx = context();
        $executor = new ProcessExecutor;
        $rollback = new RollbackManager;

        $install = (new ComposerInstall(['laravel/framework']))->withContext($ctx);
        $run = (new ComposerRun('test'))->withContext($ctx);

        $rollback->beginStep('test-step');
        $rollback->push($install);
        $rollback->push($run);

        $results = $rollback->rollbackCurrentStep($ctx, $executor);

        expect($results)->toHaveCount(1);

        $executed = $fake->executed();
        expect($executed[0]['command'])->toBe(['composer', 'remove', 'laravel/framework']);
    });

    it('resets the stack after rollback', function (): void {
        $fake = ProcessExecutor::fake();
        $ctx = context();
        $executor = new ProcessExecutor;
        $rollback = new RollbackManager;

        $rollback->beginStep('test-step');
        $rollback->push((new ComposerInstall(['pkg']))->withContext($ctx));
        $rollback->rollbackCurrentStep($ctx, $executor);

        $results = $rollback->rollbackCurrentStep($ctx, $executor);
        expect($results)->toBeEmpty();
    });

    it('isolates stacks per step', function (): void {
        $fake = ProcessExecutor::fake();
        $ctx = context();
        $executor = new ProcessExecutor;
        $rollback = new RollbackManager;

        $rollback->beginStep('step-1');
        $rollback->push((new ComposerInstall(['pkg-a']))->withContext($ctx));

        $rollback->beginStep('step-2');
        $rollback->push((new ComposerInstall(['pkg-b']))->withContext($ctx));

        $results = $rollback->rollbackCurrentStep($ctx, $executor);

        expect($results)->toHaveCount(1);

        $executed = $fake->executed();
        expect($executed[0]['command'])->toBe(['composer', 'remove', 'pkg-b']);
    });

    it('reports whether there are rollbackable actions', function (): void {
        $ctx = context();
        $rollback = new RollbackManager;

        $rollback->beginStep('test-step');
        expect($rollback->hasRollbackableActions())->toBeFalse();

        $rollback->push((new ComposerRun('test'))->withContext($ctx));
        expect($rollback->hasRollbackableActions())->toBeFalse();

        $rollback->push((new ComposerInstall(['pkg']))->withContext($ctx));
        expect($rollback->hasRollbackableActions())->toBeTrue();
    });

    it('rolls back all previous steps in reverse order', function (): void {
        $fake = ProcessExecutor::fake();
        $ctx = context();
        $executor = new ProcessExecutor;
        $rollback = new RollbackManager;

        $rollback->beginStep('step-1');
        $rollback->push((new ComposerInstall(['pkg-a']))->withContext($ctx));

        $rollback->beginStep('step-2');
        $rollback->push((new ComposerInstall(['pkg-b']))->withContext($ctx));

        $rollback->beginStep('step-3');

        $results = $rollback->rollbackAllSteps($executor);

        expect($results)->toHaveCount(2);

        $executed = $fake->executed();
        expect($executed[0]['command'])->toBe(['composer', 'remove', 'pkg-b']);
        expect($executed[1]['command'])->toBe(['composer', 'remove', 'pkg-a']);
    });

    it('uses per-step working directories during rollback', function (): void {
        $fake = ProcessExecutor::fake();
        $ctx = context();
        $executor = new ProcessExecutor;
        $rollback = new RollbackManager;

        $rollback->beginStep('clone', '/tmp/target');
        $rollback->push((new ComposerInstall(['pkg-a']))->withContext($ctx));

        $rollback->beginStep('install', '/tmp/target/project');
        $rollback->push((new ComposerInstall(['pkg-b']))->withContext($ctx));

        $rollback->beginStep('failing-step', '/tmp/target/project');

        $rollback->rollbackAllSteps($executor);

        $executed = $fake->executed();
        expect($executed[0]['cwd'])->toBe('/tmp/target/project');
        expect($executed[1]['cwd'])->toBe('/tmp/target');
    });

    it('reports whether previous steps have rollbackable actions', function (): void {
        $ctx = context();
        $rollback = new RollbackManager;

        $rollback->beginStep('step-1');
        $rollback->push((new ComposerInstall(['pkg']))->withContext($ctx));

        $rollback->beginStep('step-2');

        expect($rollback->hasPreviousRollbackableActions())->toBeTrue();
    });

});
