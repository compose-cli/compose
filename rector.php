<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessAssignFromPropertyPromotionRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Php84\Rector\Class_\PropertyHookRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/tests/tmp/**',
    ])
    ->withRules([
        PropertyHookRector::class,
        DeclareStrictTypesRector::class,
        AddOverrideAttributeToOverriddenMethodsRector::class,
        RemoveUselessAssignFromPropertyPromotionRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class,
    ])
    ->withPhpSets(php84: true)
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0);
