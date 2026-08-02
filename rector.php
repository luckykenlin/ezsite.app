<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\MethodCall\RemoveNullArgOnNullDefaultParamRector;
use Rector\EarlyReturn\Rector\If_\ChangeOrIfContinueToMultiContinueRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Php85\Rector\Property\AddOverrideAttributeToOverriddenPropertiesRector;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;
use RectorLaravel\Set\LaravelSetProvider;
use RectorPest\Set\PestLevelSetList;
use RectorPest\Set\PestSetList;

return RectorConfig::configure()
    ->withSetProviders(LaravelSetProvider::class)
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_130,
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
        LaravelSetList::LARAVEL_FACTORIES,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelSetList::LARAVEL_LEGACY_FACTORIES_TO_CLASSES,

        PestLevelSetList::UP_TO_PEST_40,
        PestSetList::PEST_CODE_QUALITY,
        PestSetList::PEST_CHAIN,
    ])
    ->withRules([
        AddGenericReturnTypeToRelationsRector::class,
    ])
    ->withImportNames(
        removeUnusedImports: true,
    )
    ->withCache(
        cacheDirectory: '/tmp/rector',
        cacheClass: FileCacheStorage::class,
    )
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/public',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withComposerBased(laravel: true)
    ->withSkip([
        AddOverrideAttributeToOverriddenMethodsRector::class,
        MakeInheritedMethodVisibilitySameAsParentRector::class,
        AddOverrideAttributeToOverriddenPropertiesRector::class,

        // The three below are rules this codebase has DECIDED AGAINST, written
        // down here so the decision is enforced instead of re-argued every time
        // the gate goes red.
        //
        // Interpolation is the house style for building a message out of a value
        // — it reads left-to-right, and `sprintf` splits the sentence from the
        // values that fill it. There is no correctness argument either way.
        EncapsedStringsToSprintfRector::class,
        // Turns one three-condition guard into three `if (…) { continue; }`
        // blocks — 3 lines into 9, for the same branch — and eats the blank line
        // Pint then wants back. A compound guard is one idea; splitting it
        // implies the conditions are independently interesting, and here they
        // are not (they are all "this is not a block").
        ChangeOrIfContinueToMultiContinueRector::class,
        // Drops an explicit `null` argument that a reader needs: rewritten,
        // `config()->set('services.pexels.key', null)` becomes
        // `config()->set('services.pexels.key')`, which scans as a GETTER. The
        // argument is the whole point of the line in the tests that use it.
        RemoveNullArgOnNullDefaultParamRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withPhpSets();
