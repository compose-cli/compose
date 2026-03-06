<?php

use Compose\Actions\Verify\VerifyAction;
use Compose\Enums\VerifyOperation;

describe('VerifyAction', function (): void {

    describe('execution', function (): void {

        it('succeeds when closure returns truthy', function (): void {
            $action = new VerifyAction(fn () => true);

            $result = $action->execute(context());

            expect($result->successful)->toBeTrue();
            expect($result->output)->toBe('Verification passed');
        });

        it('fails when closure returns falsy', function (): void {
            $action = new VerifyAction(fn () => false);

            $result = $action->execute(context());

            expect($result->successful)->toBeFalse();
            expect($result->errorOutput)->toBe('Verification failed');
        });

        it('fails when closure returns null', function (): void {
            $action = new VerifyAction(fn () => null);

            $result = $action->execute(context());

            expect($result->successful)->toBeFalse();
        });

        it('succeeds when closure returns a non-empty string', function (): void {
            $action = new VerifyAction(fn () => 'yes');

            $result = $action->execute(context());

            expect($result->successful)->toBeTrue();
        });

        it('skips string assertions with a success result', function (): void {
            $action = new VerifyAction('The User model uses HasRoles');

            $result = $action->execute(context());

            expect($result->successful)->toBeTrue();
            expect($result->output)->toContain('Skipped');
            expect($result->output)->toContain('The User model uses HasRoles');
        });

    });

    it('returns the Verify operation type', function (): void {
        $action = new VerifyAction(fn () => true);

        expect($action)->toBeOperation(VerifyOperation::Verify);
    });

    it('describes a closure assertion', function (): void {
        $action = new VerifyAction(fn () => true);

        expect($action->describe())->toBe('verify: (closure)');
    });

    it('describes a string assertion', function (): void {
        $action = new VerifyAction('Config file exists');

        expect($action->describe())->toBe('verify: Config file exists');
    });

    it('cannot be rolled back', function (): void {
        $action = new VerifyAction(fn () => true);

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->rollback())->toBeNull();
    });

});
