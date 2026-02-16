<?php

use Compose\Enums\Node;
use Compose\Step;

describe('Plan', function (): void {

    it('generates a plan from a recipe', function (): void {
        $recipe = compose('Test Recipe');
        $recipe->step('Install packages', function (Step $step): void {
            $step->composer(install: ['laravel/framework']);
        });
        $recipe->step('Add frontend', function (Step $step): void {
            $step->node(install: ['vue']);
        });

        $plan = $recipe->plan();

        expect($plan->recipeName)->toBe('Test Recipe');
        expect($plan->steps)->toHaveCount(2);

        expect($plan->steps[0]->name)->toBe('Install packages');
        expect($plan->steps[0]->commands)->toBe(['composer require laravel/framework']);
        expect($plan->steps[0]->rollbackable)->toBe([true]);

        expect($plan->steps[1]->name)->toBe('Add frontend');
        expect($plan->steps[1]->commands)->toBe(['npm install vue']);
        expect($plan->steps[1]->rollbackable)->toBe([true]);
    });

    it('includes base repo step in plan', function (): void {
        $recipe = compose('Test Recipe')
            ->base('https://github.com/laravel/laravel.git', '11.x');

        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['pkg']));

        $plan = $recipe->plan();

        expect($plan->steps)->toHaveCount(2);
        expect($plan->steps[0]->name)->toBe('Clone base repository');
        expect($plan->steps[0]->commands[0])->toContain('git clone');
        expect($plan->steps[0]->commands[0])->toContain('laravel/laravel.git');
        expect($plan->steps[0]->commands[0])->toContain('test-recipe');
        expect($plan->steps[1]->name)->toBe('Install');
    });

    it('renders to string', function (): void {
        $recipe = compose('My App');
        $recipe->step('Install', fn (Step $step) => $step->composer(install: ['laravel/framework']));

        $plan = $recipe->plan();
        $output = (string) $plan;

        expect($output)->toContain('Compose — My App');
        expect($output)->toContain('Install');
        expect($output)->toContain('composer require laravel/framework');
    });

    it('marks rollbackable commands', function (): void {
        $recipe = compose('Test');
        $recipe->step('Mixed', function (Step $step): void {
            $step
                ->composer(install: ['pkg'])
                ->composer(run: 'test');
        });

        $plan = $recipe->plan();

        expect($plan->steps[0]->rollbackable)->toBe([true, false]);
    });

    it('uses the correct node manager in plan', function (): void {
        $recipe = compose('Test')->node(Node::Yarn);
        $recipe->step('Frontend', fn (Step $step) => $step->node(install: ['vue']));

        $plan = $recipe->plan();

        expect($plan->steps[0]->commands[0])->toBe('yarn add vue');
    });

});
