<?php

use Compose\Support\PhpFile\ClassManipulator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

describe('ClassManipulator', function (): void {

    function makeManipulator(?string $code = null, string $namespace = 'App\\Models'): ClassManipulator
    {
        if ($code !== null) {
            $class = ClassType::fromCode($code);
        } else {
            $class = new ClassType('User');
        }

        return new ClassManipulator($class, new PhpNamespace($namespace));
    }

    // -------------------------------------------------------------------
    // addTrait
    // -------------------------------------------------------------------

    describe('addTrait', function (): void {

        it('adds a trait by short name', function (): void {
            $class = new ClassType('User');
            $m = new ClassManipulator($class);

            $m->addTrait('SoftDeletes');

            expect($class->getTraits())->toHaveKey('SoftDeletes');
        });

        it('adds a trait by FQCN and registers the import', function (): void {
            $ns = new PhpNamespace('App\\Models');
            $class = $ns->addClass('User');
            $m = new ClassManipulator($class, $ns);

            $m->addTrait(\Spatie\Permission\Traits\HasRoles::class);

            expect($class->getTraits())->toHaveKey(\Spatie\Permission\Traits\HasRoles::class);
            expect(array_values($ns->getUses()))->toContain(\Spatie\Permission\Traits\HasRoles::class);
        });

    });

    // -------------------------------------------------------------------
    // removeTrait
    // -------------------------------------------------------------------

    describe('removeTrait', function (): void {

        it('removes a trait', function (): void {
            $class = new ClassType('User');
            $class->addTrait('SoftDeletes');
            $m = new ClassManipulator($class);

            $m->removeTrait('SoftDeletes');

            expect($class->getTraits())->toBeEmpty();
        });

    });

    // -------------------------------------------------------------------
    // addInterface
    // -------------------------------------------------------------------

    describe('addInterface', function (): void {

        it('adds an interface by FQCN and registers the import', function (): void {
            $ns = new PhpNamespace('App\\Models');
            $class = $ns->addClass('User');
            $m = new ClassManipulator($class, $ns);

            $m->addInterface(\Illuminate\Contracts\Auth\Authenticatable::class);

            expect(array_values($class->getImplements()))->toContain(\Illuminate\Contracts\Auth\Authenticatable::class);
            expect(array_values($ns->getUses()))->toContain(\Illuminate\Contracts\Auth\Authenticatable::class);
        });

    });

    // -------------------------------------------------------------------
    // addImport / removeImport
    // -------------------------------------------------------------------

    describe('addImport', function (): void {

        it('adds a use import to the namespace', function (): void {
            $ns = new PhpNamespace('App\\Models');
            $class = $ns->addClass('User');
            $m = new ClassManipulator($class, $ns);

            $m->addImport(\Carbon\Carbon::class);

            expect(array_values($ns->getUses()))->toContain(\Carbon\Carbon::class);
        });

    });

    describe('removeImport', function (): void {

        it('removes a use import from the namespace', function (): void {
            $ns = new PhpNamespace('App\\Models');
            $ns->addUse(\Carbon\Carbon::class);
            $class = $ns->addClass('User');
            $m = new ClassManipulator($class, $ns);

            $m->removeImport(\Carbon\Carbon::class);

            expect(array_values($ns->getUses()))->not->toContain(\Carbon\Carbon::class);
        });

    });

    // -------------------------------------------------------------------
    // addMethod
    // -------------------------------------------------------------------

    describe('addMethod', function (): void {

        it('adds a public method with body', function (): void {
            $class = new ClassType('User');
            $m = new ClassManipulator($class);

            $m->addMethod('isAdmin', 'return $this->hasRole("admin");');

            expect($class->hasMethod('isAdmin'))->toBeTrue();
            $method = $class->getMethod('isAdmin');
            expect($method->getBody())->toBe('return $this->hasRole("admin");');
            expect($method->isPublic())->toBeTrue();
        });

        it('adds a method with custom visibility and return type', function (): void {
            $class = new ClassType('User');
            $m = new ClassManipulator($class);

            $m->addMethod('getTeamId', 'return $this->team_id;', 'protected', 'int');

            $method = $class->getMethod('getTeamId');
            expect($method->isProtected())->toBeTrue();
            expect($method->getReturnType())->toBe('int');
        });

    });

    // -------------------------------------------------------------------
    // addProperty
    // -------------------------------------------------------------------

    describe('addProperty', function (): void {

        it('adds a property with default value and visibility', function (): void {
            $class = new ClassType('User');
            $m = new ClassManipulator($class);

            $m->addProperty('fillable', ['name', 'email'], 'protected');

            expect($class->hasProperty('fillable'))->toBeTrue();
            $prop = $class->getProperty('fillable');
            expect($prop->getValue())->toBe(['name', 'email']);
            expect($prop->isProtected())->toBeTrue();
        });

        it('adds a typed property', function (): void {
            $class = new ClassType('User');
            $m = new ClassManipulator($class);

            $m->addProperty('name', null, 'public', 'string');

            $prop = $class->getProperty('name');
            expect($prop->getType())->toBe('string');
        });

    });

    // -------------------------------------------------------------------
    // addConstant
    // -------------------------------------------------------------------

    describe('addConstant', function (): void {

        it('adds a constant with visibility', function (): void {
            $class = new ClassType('User');
            $m = new ClassManipulator($class);

            $m->addConstant('MAX_ATTEMPTS', 5, 'protected');

            expect($class->hasConstant('MAX_ATTEMPTS'))->toBeTrue();
            $const = $class->getConstant('MAX_ATTEMPTS');
            expect($const->getValue())->toBe(5);
            expect($const->isProtected())->toBeTrue();
        });

    });

    // -------------------------------------------------------------------
    // addToArray
    // -------------------------------------------------------------------

    describe('addToArray', function (): void {

        it('appends to an existing indexed array property', function (): void {
            $class = new ClassType('User');
            $class->addProperty('fillable')->setValue(['name', 'email']);
            $m = new ClassManipulator($class);

            $m->addToArray('fillable', ['team_id', 'avatar']);

            expect($class->getProperty('fillable')->getValue())
                ->toBe(['name', 'email', 'team_id', 'avatar']);
        });

        it('deduplicates indexed arrays', function (): void {
            $class = new ClassType('User');
            $class->addProperty('fillable')->setValue(['name', 'email']);
            $m = new ClassManipulator($class);

            $m->addToArray('fillable', ['email', 'team_id']);

            expect($class->getProperty('fillable')->getValue())
                ->toBe(['name', 'email', 'team_id']);
        });

        it('merges associative arrays', function (): void {
            $class = new ClassType('User');
            $class->addProperty('casts')->setValue(['email_verified_at' => 'datetime']);
            $m = new ClassManipulator($class);

            $m->addToArray('casts', ['team_id' => 'integer']);

            expect($class->getProperty('casts')->getValue())
                ->toBe(['email_verified_at' => 'datetime', 'team_id' => 'integer']);
        });

        it('throws when property does not exist', function (): void {
            $class = new ClassType('User');
            $m = new ClassManipulator($class);

            $m->addToArray('nonexistent', ['value']);
        })->throws(RuntimeException::class, 'Property $nonexistent does not exist');

    });

    // -------------------------------------------------------------------
    // addToMethod
    // -------------------------------------------------------------------

    describe('addToMethod', function (): void {

        it('appends code to an existing method', function (): void {
            $class = new ClassType('User');
            $class->addMethod('boot')->setBody('parent::boot();');
            $m = new ClassManipulator($class);

            $m->addToMethod('boot', 'static::creating(fn ($u) => $u->uuid = Str::uuid());');

            $body = $class->getMethod('boot')->getBody();
            expect($body)->toContain('parent::boot();');
            expect($body)->toContain('static::creating');
        });

        it('throws when method does not exist', function (): void {
            $class = new ClassType('User');
            $m = new ClassManipulator($class);

            $m->addToMethod('nonexistent', 'code');
        })->throws(RuntimeException::class, 'Method nonexistent() does not exist');

    });

    // -------------------------------------------------------------------
    // removeMethod
    // -------------------------------------------------------------------

    describe('removeMethod', function (): void {

        it('removes a method', function (): void {
            $class = new ClassType('User');
            $class->addMethod('oldMethod');
            $m = new ClassManipulator($class);

            $m->removeMethod('oldMethod');

            expect($class->hasMethod('oldMethod'))->toBeFalse();
        });

    });

});
