<?php

use Compose\Builders\JsonModifyBuilder;
use Compose\Builders\ModifyBuilder;
use Compose\Payloads\ModifyOperationPayload;

describe('ModifyBuilder', function (): void {

    describe('PHP class operations', function (): void {

        it('collects addTrait operations', function (): void {
            $builder = new ModifyBuilder;
            $builder->addTrait('HasRoles');

            $ops = $builder->operations();
            expect($ops)->toHaveCount(1);
            expect($ops[0])->toBeInstanceOf(ModifyOperationPayload::class);
            expect($ops[0]->type)->toBe('add_trait');
            expect($ops[0]->arguments['trait'])->toBe('HasRoles');
        });

        it('collects addMethod with all parameters', function (): void {
            $builder = new ModifyBuilder;
            $builder->addMethod('isAdmin', 'return true;', 'protected', 'bool');

            $ops = $builder->operations();
            expect($ops[0]->type)->toBe('add_method');
            expect($ops[0]->arguments)->toBe([
                'name' => 'isAdmin',
                'body' => 'return true;',
                'visibility' => 'protected',
                'returnType' => 'bool',
            ]);
        });

        it('collects addProperty with all parameters', function (): void {
            $builder = new ModifyBuilder;
            $builder->addProperty('items', [], 'protected', 'array');

            $ops = $builder->operations();
            expect($ops[0]->type)->toBe('add_property');
            expect($ops[0]->arguments)->toBe([
                'name' => 'items',
                'default' => [],
                'visibility' => 'protected',
                'type' => 'array',
            ]);
        });

        it('collects addConstant operations', function (): void {
            $builder = new ModifyBuilder;
            $builder->addConstant('VERSION', '1.0', 'public');

            $ops = $builder->operations();
            expect($ops[0]->type)->toBe('add_constant');
            expect($ops[0]->arguments['name'])->toBe('VERSION');
            expect($ops[0]->arguments['value'])->toBe('1.0');
        });

        it('collects addToArray operations', function (): void {
            $builder = new ModifyBuilder;
            $builder->addToArray('fillable', ['team_id']);

            $ops = $builder->operations();
            expect($ops[0]->type)->toBe('add_to_array');
            expect($ops[0]->arguments['property'])->toBe('fillable');
            expect($ops[0]->arguments['values'])->toBe(['team_id']);
        });

        it('collects addToMethod operations', function (): void {
            $builder = new ModifyBuilder;
            $builder->addToMethod('boot', 'static::creating(fn () => null);');

            $ops = $builder->operations();
            expect($ops[0]->type)->toBe('add_to_method');
            expect($ops[0]->arguments['method'])->toBe('boot');
        });

        it('collects remove operations', function (): void {
            $builder = new ModifyBuilder;
            $builder->removeTrait('SoftDeletes')
                ->removeMethod('oldMethod')
                ->removeImport('Old\\Import');

            $ops = $builder->operations();
            expect($ops)->toHaveCount(3);
            expect($ops[0]->type)->toBe('remove_trait');
            expect($ops[1]->type)->toBe('remove_method');
            expect($ops[2]->type)->toBe('remove_import');
        });

        it('collects addInterface operations', function (): void {
            $builder = new ModifyBuilder;
            $builder->addInterface('Countable');

            $ops = $builder->operations();
            expect($ops[0]->type)->toBe('add_interface');
            expect($ops[0]->arguments['interface'])->toBe('Countable');
        });

        it('collects addImport operations', function (): void {
            $builder = new ModifyBuilder;
            $builder->addImport(\Carbon\Carbon::class);

            $ops = $builder->operations();
            expect($ops[0]->type)->toBe('add_import');
            expect($ops[0]->arguments['class'])->toBe(\Carbon\Carbon::class);
        });

    });

    describe('text operations', function (): void {

        it('collects replace operations', function (): void {
            $builder = new ModifyBuilder;
            $builder->replace('old', 'new');

            $ops = $builder->operations();
            expect($ops[0]->type)->toBe('replace');
            expect($ops[0]->arguments)->toBe(['search' => 'old', 'replace' => 'new']);
        });

        it('collects replaceRegex operations', function (): void {
            $builder = new ModifyBuilder;
            $builder->replaceRegex('/\d+/', 'NUM');

            $ops = $builder->operations();
            expect($ops[0]->type)->toBe('replace_regex');
        });

        it('collects prepend and append operations', function (): void {
            $builder = new ModifyBuilder;
            $builder->prepend('header')->append('footer');

            $ops = $builder->operations();
            expect($ops[0]->type)->toBe('prepend');
            expect($ops[1]->type)->toBe('append');
        });

        it('collects insertAfter and insertBefore operations', function (): void {
            $builder = new ModifyBuilder;
            $builder->insertAfter('marker', 'after-content')
                ->insertBefore('marker', 'before-content');

            $ops = $builder->operations();
            expect($ops[0]->type)->toBe('insert_after');
            expect($ops[0]->arguments['marker'])->toBe('marker');
            expect($ops[1]->type)->toBe('insert_before');
        });

    });

    describe('json delegation', function (): void {

        it('delegates json operations to JsonModifyBuilder', function (): void {
            $builder = new ModifyBuilder;
            $builder->json(fn (JsonModifyBuilder $j) => $j
                ->set('name', 'app')
                ->merge('scripts', ['build' => 'vite'])
                ->remove('old')
                ->push('tags', 'php')
            );

            $ops = $builder->operations();
            expect($ops)->toHaveCount(4);
            expect($ops[0]->type)->toBe('json_set');
            expect($ops[1]->type)->toBe('json_merge');
            expect($ops[2]->type)->toBe('json_remove');
            expect($ops[3]->type)->toBe('json_push');
        });

    });

    describe('chaining', function (): void {

        it('supports fluent method chaining across all operation types', function (): void {
            $builder = new ModifyBuilder;

            $result = $builder
                ->addTrait('HasRoles')
                ->addMethod('isAdmin', 'return true;')
                ->replace('old', 'new')
                ->append('footer');

            expect($result)->toBeInstanceOf(ModifyBuilder::class);
            expect($builder->operations())->toHaveCount(4);
        });

    });

});
