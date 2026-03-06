<?php

use Compose\Actions\Test\TestAction;
use Compose\Enums\VerifyOperation;

describe('TestAction', function (): void {

    it('generates an artisan test command with filter', function (): void {
        $action = (new TestAction('tests/Feature/TeamTest.php'))->withContext(context());

        expect($action)->toGenerateCommand('php artisan test --filter=tests/Feature/TeamTest.php');
    });

    it('returns the Test operation type', function (): void {
        $action = (new TestAction('tests/Feature/TeamTest.php'))->withContext(context());

        expect($action)->toBeOperation(VerifyOperation::Test);
    });

    it('uses a custom php binary from context', function (): void {
        $action = (new TestAction('tests/Feature/TeamTest.php'))->withContext(context(phpBinary: '/usr/bin/php8.3'));

        expect($action)->toGenerateCommand('/usr/bin/php8.3 artisan test --filter=tests/Feature/TeamTest.php');
    });

    it('returns a preflight command', function (): void {
        $action = (new TestAction('tests/Feature/TeamTest.php'))->withContext(context());

        $preflight = $action->preflight();

        expect($preflight)->not->toBeNull();
        expect($preflight->toString())->toBe('php artisan --version');
    });

    it('describes the test path', function (): void {
        $action = new TestAction('tests/Feature/TeamTest.php');

        expect($action->describe())->toBe('test: tests/Feature/TeamTest.php');
    });

    it('cannot be rolled back', function (): void {
        $action = (new TestAction('tests/Feature/TeamTest.php'))->withContext(context());

        expect($action->canBeRolledBack())->toBeFalse();
        expect($action->rollback())->toBeNull();
    });

});
