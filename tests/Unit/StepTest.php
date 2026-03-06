<?php

use Compose\Actions\Artisan\ArtisanAction;
use Compose\Actions\Env\EnvAction;
use Compose\Actions\File\AppendFile;
use Compose\Actions\File\CopyFile;
use Compose\Actions\File\CreateFile;
use Compose\Actions\File\DeleteFile;
use Compose\Actions\File\ReadFile;
use Compose\Actions\Git\GitAdd;
use Compose\Actions\Git\GitCommit;
use Compose\Actions\Sink;
use Compose\Actions\Test\TestAction;
use Compose\Actions\Verify\VerifyAction;
use Compose\Builders\Artisan;
use Compose\Builders\EnvBuilder;
use Compose\Step;

describe('Step', function (): void {

    it('adds git add and git commit operations when commit is called with a message', function (): void {
        $step = new Step(name: 'Test step');

        $step->commit('Initial commit');

        $operations = $step->operations();

        expect($operations)->toHaveCount(2);
        expect($operations[0])->toBeInstanceOf(GitAdd::class);
        expect($operations[1])->toBeInstanceOf(GitCommit::class);
        expect($operations[1]->message)->toBe('Initial commit');
    });

    it('adds git add and git commit with null message when commit is called without arguments', function (): void {
        $step = new Step(name: 'Test step');

        $step->commit();

        $operations = $step->operations();

        expect($operations)->toHaveCount(2);
        expect($operations[0])->toBeInstanceOf(GitAdd::class);
        expect($operations[1])->toBeInstanceOf(GitCommit::class);
        expect($operations[1]->message)->toBeNull();
    });

    it('appends commit operations after existing operations', function (): void {
        $step = new Step(
            name: 'Test step',
            callback: function (Step $step): void {
                $step->composer(install: ['laravel/framework']);
                $step->commit('After install');
            },
        );

        $step->resolveOperations();

        $operations = $step->operations();

        expect($operations)->toHaveCount(3);
        expect($operations[0])->not->toBeInstanceOf(GitAdd::class);
        expect($operations[1])->toBeInstanceOf(GitAdd::class);
        expect($operations[2])->toBeInstanceOf(GitCommit::class);
    });

    it('supports chaining commit with other fluent methods', function (): void {
        $step = new Step(
            name: 'Test step',
            callback: function (Step $step): void {
                $step
                    ->composer(install: ['laravel/framework'])
                    ->commit('Install laravel')
                    ->composer(dev: ['pestphp/pest']);
            },
        );

        $step->resolveOperations();

        $operations = $step->operations();

        expect($operations)->toHaveCount(4);
        expect($operations[1])->toBeInstanceOf(GitAdd::class);
        expect($operations[2])->toBeInstanceOf(GitCommit::class);
        expect($operations[2]->message)->toBe('Install laravel');
    });

    it('adds a single artisan action from a string', function (): void {
        $step = new Step(name: 'Test step');

        $step->artisan('make:model Team -mf');

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(ArtisanAction::class);
        expect($operations[0]->command)->toBe('make:model Team -mf');
    });

    it('adds multiple artisan actions from a closure', function (): void {
        $step = new Step(name: 'Test step');

        $step->artisan(fn (Artisan $a) => $a
            ->run('make:controller TeamController --api')
            ->run('make:resource TeamResource')
        );

        $operations = $step->operations();

        expect($operations)->toHaveCount(2);
        expect($operations[0])->toBeInstanceOf(ArtisanAction::class);
        expect($operations[0]->command)->toBe('make:controller TeamController --api');
        expect($operations[1]->command)->toBe('make:resource TeamResource');
    });

    // -------------------------------------------------------------------
    // File Action Wiring
    // -------------------------------------------------------------------

    it('queues a CreateFile action from create()', function (): void {
        $step = new Step(name: 'Test step');

        $step->create('config/app.php', '<?php return [];');

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(CreateFile::class);
        expect($operations[0]->path)->toBe('config/app.php');
        expect($operations[0]->contents)->toBe('<?php return [];');
        expect($operations[0]->overwrite)->toBeTrue();
    });

    it('queues a CreateFile action with overwrite false', function (): void {
        $step = new Step(name: 'Test step');

        $step->create('file.txt', 'data', overwrite: false);

        expect($step->operations()[0]->overwrite)->toBeFalse();
    });

    it('queues a ReadFile action from read()', function (): void {
        $step = new Step(name: 'Test step');

        $step->read('config/app.php');

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(ReadFile::class);
        expect($operations[0]->path)->toBe('config/app.php');
    });

    it('queues a DeleteFile action from delete()', function (): void {
        $step = new Step(name: 'Test step');

        $step->delete('one.txt', 'two.txt');

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(DeleteFile::class);
        expect($operations[0]->paths)->toBe(['one.txt', 'two.txt']);
    });

    it('queues a Sink action from sink()', function (): void {
        $step = new Step(name: 'Test step');

        $step->sink('https://example.com/file.yml', 'local.yml');

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(Sink::class);
        expect($operations[0]->from)->toBe('https://example.com/file.yml');
        expect($operations[0]->to)->toBe('local.yml');
    });

    it('queues a CopyFile action from copy()', function (): void {
        $step = new Step(name: 'Test step');

        $step->copy('source.txt', 'dest.txt');

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(CopyFile::class);
        expect($operations[0]->from)->toBe('source.txt');
        expect($operations[0]->to)->toBe('dest.txt');
        expect($operations[0]->overwrite)->toBeTrue();
    });

    it('queues an AppendFile action from append()', function (): void {
        $step = new Step(name: 'Test step');

        $step->append('routes/api.php', "\nRoute::get('/teams');");

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(AppendFile::class);
        expect($operations[0]->path)->toBe('routes/api.php');
        expect($operations[0]->contents)->toBe("\nRoute::get('/teams');");
    });

    it('chains file operations together', function (): void {
        $step = new Step(name: 'Test step');

        $step
            ->create('file.txt', 'hello')
            ->copy('file.txt', 'backup.txt')
            ->append('file.txt', ' world')
            ->read('file.txt')
            ->delete('backup.txt');

        expect($step->operations())->toHaveCount(5);
        expect($step->operations()[0])->toBeInstanceOf(CreateFile::class);
        expect($step->operations()[1])->toBeInstanceOf(CopyFile::class);
        expect($step->operations()[2])->toBeInstanceOf(AppendFile::class);
        expect($step->operations()[3])->toBeInstanceOf(ReadFile::class);
        expect($step->operations()[4])->toBeInstanceOf(DeleteFile::class);
    });

    it('can be constructed without a context', function (): void {
        $step = new Step(name: 'Test step');

        expect($step->name)->toBe('Test step');
        expect($step->operations())->toBeEmpty();
    });

    // -------------------------------------------------------------------
    // Env Action Wiring
    // -------------------------------------------------------------------

    it('queues an EnvAction from env() with an array', function (): void {
        $step = new Step(name: 'Test step');

        $step->env(['APP_NAME' => 'My App', 'APP_ENV' => 'production']);

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(EnvAction::class);
        expect($operations[0]->path)->toBe('.env');
        expect($operations[0]->operations)->toHaveCount(2);
    });

    it('queues an EnvAction from env() with a closure', function (): void {
        $step = new Step(name: 'Test step');

        $step->env(fn (EnvBuilder $env) => $env
            ->set('APP_NAME', 'My App')
            ->remove('APP_EXAMPLE')
        );

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(EnvAction::class);
        expect($operations[0]->operations)->toHaveCount(2);
    });

    it('passes a custom path to EnvAction', function (): void {
        $step = new Step(name: 'Test step');

        $step->env(['KEY' => 'value'], path: '.env.example');

        expect($step->operations()[0]->path)->toBe('.env.example');
    });

    it('chains env with other methods', function (): void {
        $step = new Step(name: 'Test step');

        $step
            ->create('file.txt', 'hello')
            ->env(['APP_NAME' => 'My App'])
            ->delete('file.txt');

        expect($step->operations())->toHaveCount(3);
        expect($step->operations()[0])->toBeInstanceOf(CreateFile::class);
        expect($step->operations()[1])->toBeInstanceOf(EnvAction::class);
        expect($step->operations()[2])->toBeInstanceOf(DeleteFile::class);
    });

    it('chains artisan with other methods', function (): void {
        $step = new Step(
            name: 'Test step',
            callback: function (Step $step): void {
                $step
                    ->composer(install: ['laravel/framework'])
                    ->artisan('migrate')
                    ->commit('Setup');
            },
        );

        $step->resolveOperations();

        $operations = $step->operations();

        expect($operations)->toHaveCount(4);
        expect($operations[1])->toBeInstanceOf(ArtisanAction::class);
        expect($operations[2])->toBeInstanceOf(GitAdd::class);
        expect($operations[3])->toBeInstanceOf(GitCommit::class);
    });

});

// -------------------------------------------------------------------
// Conditional Execution
// -------------------------------------------------------------------

describe('conditional execution', function (): void {

    it('when() executes callback when condition is true', function (): void {
        $step = new Step(name: 'Test step');

        $step->when(true, fn (Step $s) => $s->artisan('migrate'));

        expect($step->operations())->toHaveCount(1);
        expect($step->operations()[0])->toBeInstanceOf(ArtisanAction::class);
    });

    it('when() skips callback when condition is false', function (): void {
        $step = new Step(name: 'Test step');

        $step->when(false, fn (Step $s) => $s->artisan('migrate'));

        expect($step->operations())->toBeEmpty();
    });

    it('when() evaluates a Closure condition returning true', function (): void {
        $step = new Step(name: 'Test step');

        $step->when(fn () => true, fn (Step $s) => $s->create('file.txt', 'hello'));

        expect($step->operations())->toHaveCount(1);
        expect($step->operations()[0])->toBeInstanceOf(CreateFile::class);
    });

    it('when() evaluates a Closure condition returning false', function (): void {
        $step = new Step(name: 'Test step');

        $step->when(fn () => false, fn (Step $s) => $s->create('file.txt', 'hello'));

        expect($step->operations())->toBeEmpty();
    });

    it('unless() skips callback when condition is true', function (): void {
        $step = new Step(name: 'Test step');

        $step->unless(true, fn (Step $s) => $s->artisan('migrate'));

        expect($step->operations())->toBeEmpty();
    });

    it('unless() executes callback when condition is false', function (): void {
        $step = new Step(name: 'Test step');

        $step->unless(false, fn (Step $s) => $s->composer(dev: ['laravel/telescope']));

        expect($step->operations())->toHaveCount(1);
    });

    it('unless() evaluates a Closure condition', function (): void {
        $step = new Step(name: 'Test step');

        $step->unless(fn () => false, fn (Step $s) => $s->create('file.txt', 'data'));

        expect($step->operations())->toHaveCount(1);
        expect($step->operations()[0])->toBeInstanceOf(CreateFile::class);
    });

    it('tap() always executes the callback', function (): void {
        $step = new Step(name: 'Test step');
        $called = false;

        $step->tap(function (Step $s) use (&$called): void {
            $called = true;
            $s->create('debug.log', 'tap was here');
        });

        expect($called)->toBeTrue();
        expect($step->operations())->toHaveCount(1);
        expect($step->operations()[0])->toBeInstanceOf(CreateFile::class);
    });

    it('chains when, unless, and other methods fluently', function (): void {
        $step = new Step(name: 'Test step');

        $step
            ->composer(install: ['laravel/framework'])
            ->when(true, fn (Step $s) => $s->artisan('migrate'))
            ->unless(false, fn (Step $s) => $s->create('config.php', '<?php return [];'))
            ->when(false, fn (Step $s) => $s->artisan('should-not-run'))
            ->delete('temp.txt');

        $operations = $step->operations();

        expect($operations)->toHaveCount(4);
        expect($operations[1])->toBeInstanceOf(ArtisanAction::class);
        expect($operations[2])->toBeInstanceOf(CreateFile::class);
        expect($operations[3])->toBeInstanceOf(DeleteFile::class);
    });

});

// -------------------------------------------------------------------
// Verification Gates
// -------------------------------------------------------------------

describe('verification gates', function (): void {

    it('verify() with Closure queues a VerifyAction with allowFailure true', function (): void {
        $step = new Step(name: 'Test step');

        $step->verify(fn () => true);

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(VerifyAction::class);
        expect($operations[0]->allowFailure)->toBeTrue();
    });

    it('verify() with string queues a VerifyAction with allowFailure true', function (): void {
        $step = new Step(name: 'Test step');

        $step->verify('The User model uses HasRoles');

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(VerifyAction::class);
        expect($operations[0]->assertion)->toBe('The User model uses HasRoles');
        expect($operations[0]->allowFailure)->toBeTrue();
    });

    it('assert() with Closure queues a VerifyAction with allowFailure false', function (): void {
        $step = new Step(name: 'Test step');

        $step->assert(fn () => file_exists('something'));

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(VerifyAction::class);
        expect($operations[0]->allowFailure)->toBeFalse();
    });

    it('test() with a single path queues one TestAction', function (): void {
        $step = new Step(name: 'Test step');

        $step->test('tests/Feature/TeamTest.php');

        $operations = $step->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0])->toBeInstanceOf(TestAction::class);
        expect($operations[0]->path)->toBe('tests/Feature/TeamTest.php');
        expect($operations[0]->allowFailure)->toBeTrue();
    });

    it('test() with multiple paths queues multiple TestActions', function (): void {
        $step = new Step(name: 'Test step');

        $step->test(['tests/Feature/TeamTest.php', 'tests/Feature/UserTest.php']);

        $operations = $step->operations();

        expect($operations)->toHaveCount(2);
        expect($operations[0])->toBeInstanceOf(TestAction::class);
        expect($operations[0]->path)->toBe('tests/Feature/TeamTest.php');
        expect($operations[1])->toBeInstanceOf(TestAction::class);
        expect($operations[1]->path)->toBe('tests/Feature/UserTest.php');
    });

    it('chains verify, assert, and test with other operations', function (): void {
        $step = new Step(name: 'Test step');

        $step
            ->composer(install: ['laravel/framework'])
            ->verify(fn () => true)
            ->test('tests/Feature/TeamTest.php')
            ->assert(fn () => true)
            ->artisan('migrate');

        $operations = $step->operations();

        expect($operations)->toHaveCount(5);
        expect($operations[1])->toBeInstanceOf(VerifyAction::class);
        expect($operations[2])->toBeInstanceOf(TestAction::class);
        expect($operations[3])->toBeInstanceOf(VerifyAction::class);
        expect($operations[4])->toBeInstanceOf(ArtisanAction::class);
    });

});
