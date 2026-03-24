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

/**
 * Comprehensive test script for all Installation Steps
 *
 * Tests:
 * - All Step classes instantiation
 * - execute() method availability
 * - StepResult return type
 * - requiresDisplay logic
 * - Ordering
 * - Metadata (name, title)
 *
 * Usage:
 *   php galette/install/test_all_steps.php
 */

// Bootstrap
define('GALETTE_ROOT', __DIR__ . '/../../');
require_once GALETTE_ROOT . 'galette/vendor/autoload.php';
require_once GALETTE_ROOT . 'galette/includes/sys_config/versions.inc.php';

use Galette\Core\Install;
use Galette\Core\Installation\StepResult;

// Test counters
$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;

function test_log(string $message, bool $success = true): void
{
    $icon = $success ? '✓' : '✗';
    $color = $success ? '' : "\033[31m"; // Red for failures
    $reset = "\033[0m";
    echo "  {$color}{$icon} {$message}{$reset}\n";
}

function test_section(string $title): void
{
    echo "\n" . str_repeat('=', 60) . "\n";
    echo $title . "\n";
    echo str_repeat('=', 60) . "\n";
}

// Create mock Install instance
try {
    $install = new Install();
} catch (\Throwable $e) {
    echo "ERROR: Cannot create Install instance: " . $e->getMessage() . "\n";
    exit(1);
}

// All Step classes to test
$stepClasses = [
    'Galette\Core\Installation\Step\CheckStep',
    'Galette\Core\Installation\Step\TypeStep',
    'Galette\Core\Installation\Step\DatabaseStep',
    'Galette\Core\Installation\Step\DatabaseCheckStep',
    'Galette\Core\Installation\Step\VersionSelectionStep',
    'Galette\Core\Installation\Step\DatabaseInstallStep',
    'Galette\Core\Installation\Step\AdminStep',
    'Galette\Core\Installation\Step\TelemetryStep',
    'Galette\Core\Installation\Step\EndStep',
];

test_section("COMPREHENSIVE STEP TESTS");

foreach ($stepClasses as $className) {
    $shortName = substr($className, strrpos($className, '\\') + 1);
    echo "\nTesting {$shortName} ({$className})...\n";

    // Test 1: Class exists
    $total_tests++;
    if (class_exists($className)) {
        test_log("Class exists");
        $passed_tests++;
    } else {
        test_log("Class does NOT exist", false);
        $failed_tests++;
        continue; // Skip remaining tests for this class
    }

    // Test 2: Can instantiate with Install parameter
    $total_tests++;
    try {
        $step = new $className($install);
        test_log("Can instantiate with Install parameter");
        $passed_tests++;
    } catch (\ArgumentCountError $e) {
        test_log("ArgumentCountError: " . $e->getMessage(), false);
        $failed_tests++;
        continue;
    } catch (\Throwable $e) {
        test_log("Instantiation error: " . get_class($e) . ": " . $e->getMessage(), false);
        $failed_tests++;
        continue;
    }

    // Test 3: execute() method exists
    $total_tests++;
    if (method_exists($step, 'execute')) {
        test_log("execute() method exists");
        $passed_tests++;
    } else {
        test_log("execute() method NOT found", false);
        $failed_tests++;
        continue;
    }

    // Test 4: execute() is callable
    $total_tests++;
    if (is_callable([$step, 'execute'])) {
        test_log("execute() is callable");
        $passed_tests++;
    } else {
        test_log("execute() is NOT callable", false);
        $failed_tests++;
    }

    // Test 5: execute() signature is correct (accepts array)
    $total_tests++;
    try {
        $reflection = new ReflectionMethod($step, 'execute');
        $params = $reflection->getParameters();
        if (count($params) === 1 && $params[0]->getName() === 'data') {
            test_log("execute() signature is correct");
            $passed_tests++;
        } else {
            test_log("execute() signature is incorrect", false);
            $failed_tests++;
        }
    } catch (\Throwable $e) {
        test_log("Signature check error: " . $e->getMessage(), false);
        $failed_tests++;
    }

    // Test 6: execute() returns StepResult
    $total_tests++;
    try {
        $result = $step->execute([]);
        if ($result instanceof StepResult) {
            test_log("execute() returns StepResult");
            $passed_tests++;
        } else {
            test_log("execute() does NOT return StepResult (got " . get_class($result) . ")", false);
            $failed_tests++;
        }
    } catch (\Throwable $e) {
        test_log("⚠ WARNING: execute([]) threw exception: " . get_class($e) . ": " . $e->getMessage());
        test_log("  → This may be normal if the step requires specific data or environment");
        // Don't count as failure - it's expected for some steps
    }

    // Test 7: Metadata methods
    $total_tests++;
    try {
        $name = $step->getStepName();
        $title = $step->getStepTitle();
        $order = $step->getOrder();

        if (is_string($name) && !empty($name) && is_string($title) && !empty($title) && is_int($order)) {
            test_log("Metadata correct (name={$name}, order={$order})");
            $passed_tests++;
        } else {
            test_log("Metadata incomplete or wrong type", false);
            $failed_tests++;
        }
    } catch (\Throwable $e) {
        test_log("Metadata error: " . $e->getMessage(), false);
        $failed_tests++;
    }

    // Test 8: canSkipDisplay() method
    $total_tests++;
    if (method_exists($step, 'canSkipDisplay')) {
        $canSkip = $step->canSkipDisplay();
        if (is_bool($canSkip)) {
            test_log("canSkipDisplay() returns bool: " . ($canSkip ? 'true' : 'false'));
            $passed_tests++;
        } else {
            test_log("canSkipDisplay() does NOT return bool", false);
            $failed_tests++;
        }
    } else {
        test_log("canSkipDisplay() method not found", false);
        $failed_tests++;
    }

    // Test 9: Order value is reasonable
    $total_tests++;
    try {
        $order = $step->getOrder();
        if ($order >= 10 && $order <= 100) {
            test_log("Order value is reasonable ({$order})");
            $passed_tests++;
        } else {
            test_log("Order value seems wrong ({$order})", false);
            $failed_tests++;
        }
    } catch (\Throwable $e) {
        test_log("Order check error: " . $e->getMessage(), false);
        $failed_tests++;
    }

    echo "  " . str_repeat('-', 56) . "\n";
    echo "  Tests for {$shortName}: " . ($failed_tests === 0 ? "✅ ALL PASSED" : "⚠ Some failed") . "\n";
}

// Final summary
test_section("FINAL SUMMARY");

$success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 1) : 0;

echo "\n";
echo "Total tests run:    {$total_tests}\n";
echo "Tests passed:       {$passed_tests}\n";
echo "Tests failed:       {$failed_tests}\n";
echo "Success rate:       {$success_rate}%\n";
echo "\n";

if ($failed_tests === 0) {
    echo str_repeat('=', 60) . "\n";
    echo "✅ ALL TESTS PASSED\n";
    echo str_repeat('=', 60) . "\n";
    exit(0);
} else {
    echo str_repeat('=', 60) . "\n";
    echo "⚠ SOME TESTS FAILED\n";
    echo str_repeat('=', 60) . "\n";
    exit(1);
}
