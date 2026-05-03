<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Rector\CodeQuality\Rector as CodeQuality;

define('GALETTE_ROOT', __DIR__ . '/galette/');
require_once GALETTE_ROOT . '/includes/sys_config/versions.inc.php';
require_once GALETTE_ROOT . '/includes/sys_config/paths.inc.php';

return RectorConfig::configure()
    ->withPaths([
        GALETTE_ROOT . '/lib',
        GALETTE_ROOT . '/includes',
        GALETTE_ROOT . '/install',
        GALETTE_ROOT . '/webroot',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector::class,
    ])
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withCache(
        cacheDirectory: sys_get_temp_dir() . '/galette-rector',
        cacheClass: FileCacheStorage::class
    )
    ->withParallel(timeoutSeconds: 300)
    // uncomment to reach your current PHP version
    ->withPhpSets(php83: true)
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
        CodeQuality\Foreach_\UnusedForeachValueToArrayKeysRector::class,
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
        CodeQuality\If_\CombineIfRector::class,
        CodeQuality\If_\CompleteMissingIfElseBracketRector::class,
        CodeQuality\If_\ConsecutiveNullCompareReturnsToNullCoalesceQueueRector::class,
        // TODO maybe later CodeQuality\If_\ExplicitBoolCompareRector::class,
        CodeQuality\If_\ShortenElseIfRector::class,
        CodeQuality\If_\SimplifyIfElseToTernaryRector::class,
        CodeQuality\If_\SimplifyIfNotNullReturnRector::class,
        CodeQuality\If_\SimplifyIfNullableReturnRector::class,
        CodeQuality\If_\SimplifyIfReturnBoolRector::class,
        CodeQuality\Include_\AbsolutizeRequireAndIncludePathRector::class,
        CodeQuality\LogicalAnd\AndAssignsToSeparateLinesRector::class,
        CodeQuality\LogicalAnd\LogicalToBooleanRector::class,
        CodeQuality\NotEqual\CommonNotEqualRector::class,
        CodeQuality\Ternary\UnnecessaryTernaryExpressionRector::class,
    ])
    ;
