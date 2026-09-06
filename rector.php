<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use Galette\Tests\Rector\AddNamedArgumentsRector;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector as CodeQuality;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector as DeadCode;

define('GALETTE_ROOT', __DIR__ . '/galette/');
// class constants such as CsvOut::DEFAULT_DIRECTORY are built from these,
// they must be defined before reflection autoloads any Galette class
require_once GALETTE_ROOT . 'includes/sys_config/versions.inc.php';
require_once GALETTE_ROOT . 'includes/sys_config/paths.inc.php';

return RectorConfig::configure()
    ->withPaths([
        GALETTE_ROOT . 'index.php',
        GALETTE_ROOT . 'cron',
        GALETTE_ROOT . 'lib',
        GALETTE_ROOT . 'includes',
        GALETTE_ROOT . 'install',
        GALETTE_ROOT . 'webroot',
        __DIR__ . '/tests',
    ])
    // No argument on purpose: both the target version and the PHP sets applied are
    // read from composer.json ("php": ">=8.3"), so the next PHP bump happens there only.
    ->withPhpSets()
    ->withSkip([
        // runtime scratch directory, entirely gitignored: holds the compiled Twig cache
        __DIR__ . '/tests/tests-data',
        // written by the test install, not versioned
        __DIR__ . '/tests/config/*/local_config.inc.php',
    ])
    ->withCache(
        cacheDirectory: sys_get_temp_dir() . '/galette-rector',
        cacheClass: FileCacheStorage::class
    )
    ->withParallel(timeoutSeconds: 300)
    // Deliberately a hand-picked subset rather than withPreparedSets(): only rules whose
    // output is unambiguously better are enabled, the opinionated ones (ternary,
    // early-return, inlining rewrites) are left out.
    ->withRules([
        CodeQuality\Assign\CombinedAssignRector::class,
        CodeQuality\BooleanAnd\RemoveUselessIsObjectCheckRector::class,
        CodeQuality\BooleanAnd\SimplifyEmptyArrayCheckRector::class,
        CodeQuality\BooleanNot\ReplaceMultipleBooleanNotRector::class,
        CodeQuality\Catch_\ThrowWithPreviousExceptionRector::class,
        CodeQuality\Empty_\SimplifyEmptyCheckOnEmptyArrayRector::class,
        CodeQuality\Expression\InlineIfToExplicitIfRector::class,
        CodeQuality\Expression\TernaryFalseExpressionToIfRector::class,
        CodeQuality\For_\ForRepeatedCountToOwnVariableRector::class,
        CodeQuality\Foreach_\ForeachItemsAssignToEmptyArrayToAssignRector::class,
        CodeQuality\Foreach_\ForeachToInArrayRector::class,
        CodeQuality\Foreach_\SimplifyForeachToCoalescingRector::class,
        CodeQuality\FuncCall\ChangeArrayPushToArrayAssignRector::class,
        CodeQuality\FuncCall\CompactToVariablesRector::class,
        CodeQuality\FuncCall\InlineIsAInstanceOfRector::class,
        CodeQuality\FuncCall\IsAWithStringWithThirdArgumentRector::class,
        CodeQuality\FuncCall\RemoveSoleValueSprintfRector::class,
        CodeQuality\FuncCall\SetTypeToCastRector::class,
        CodeQuality\FuncCall\SimplifyFuncGetArgsCountRector::class,
        CodeQuality\FuncCall\SimplifyInArrayValuesRector::class,
        CodeQuality\FuncCall\SimplifyStrposLowerRector::class,
        CodeQuality\FuncCall\UnwrapSprintfOneArgumentRector::class,
        CodeQuality\Identical\BooleanNotIdenticalToNotIdenticalRector::class,
        CodeQuality\Identical\SimplifyArraySearchRector::class,
        CodeQuality\Identical\SimplifyConditionsRector::class,
        CodeQuality\Identical\StrlenZeroToIdenticalEmptyStringRector::class,
        CodeQuality\If_\CompleteMissingIfElseBracketRector::class,
        CodeQuality\If_\ConsecutiveNullCompareReturnsToNullCoalesceQueueRector::class,
        // TODO maybe later, successors of the deprecated ExplicitBoolCompareRector:
        // CodeQuality\If_\ArrayExplicitBoolCompareRector::class,
        // CodeQuality\If_\ObjectExplicitBoolCompareRector::class,
        CodeQuality\If_\SimplifyIfNotNullReturnRector::class,
        CodeQuality\If_\SimplifyIfNullableReturnRector::class,
        CodeQuality\If_\SimplifyIfReturnBoolRector::class,
        CodeQuality\Include_\AbsolutizeRequireAndIncludePathRector::class,
        CodeQuality\LogicalAnd\AndAssignsToSeparateLinesRector::class,
        CodeQuality\LogicalAnd\LogicalToBooleanRector::class,
        CodeQuality\NotEqual\CommonNotEqualRector::class,
        CodeQuality\Ternary\UnnecessaryTernaryExpressionRector::class,
        DeadCode\Assign\RemoveUnusedVariableAssignRector::class,
        // covers CodeQuality\CallLike\AddNameTo{Boolean,Null}ArgumentRector too, which
        // ignore @no-named-arguments and named PHPUnit's willReturn(value: …)
        AddNamedArgumentsRector::class,
    ])
    ;
