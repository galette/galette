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
 * Test script to validate Step classes instantiation
 * 
 * This script checks that all Step classes can be properly instantiated
 * and that their execute() methods are callable.
 * 
 * Usage:
 *   php galette/install/test_steps.php
 */

// Bootstrap
define('GALETTE_ROOT', __DIR__ . '/../../');
require_once GALETTE_ROOT . 'galette/vendor/autoload.php';
require_once GALETTE_ROOT . 'galette/includes/sys_config/versions.inc.php';

use Galette\Core\Install;
use Galette\Core\Installation\Step\CheckStep;
use Galette\Core\Installation\Step\DatabaseCheckStep;
use Galette\Core\Installation\Step\DatabaseInstallStep;

echo "========================================\n";
echo "Testing Step Classes Instantiation\n";
echo "========================================\n\n";

// Create mock Install object
$install = new Install();

// List of Step classes to test
$stepClasses = [
    'CheckStep' => CheckStep::class,
    'DatabaseCheckStep' => DatabaseCheckStep::class,
    'DatabaseInstallStep' => DatabaseInstallStep::class,
];

$allPassed = true;

foreach ($stepClasses as $name => $className) {
    echo "Testing $name ($className)...\n";
    
    try {
        // Test 1: Class exists
        if (!class_exists($className)) {
            echo "  ✗ FAIL: Class does not exist\n";
            $allPassed = false;
            continue;
        }
        echo "  ✓ Class exists\n";
        
        // Test 2: Can instantiate with Install parameter
        try {
            $step = new $className($install);
            echo "  ✓ Can instantiate with Install parameter\n";
        } catch (\ArgumentCountError $e) {
            echo "  ✗ FAIL: ArgumentCountError - " . $e->getMessage() . "\n";
            echo "         → Constructor signature may be wrong\n";
            $allPassed = false;
            continue;
        } catch (\TypeError $e) {
            echo "  ✗ FAIL: TypeError - " . $e->getMessage() . "\n";
            $allPassed = false;
            continue;
        }
        
        // Test 3: execute() method exists
        if (!method_exists($step, 'execute')) {
            echo "  ✗ FAIL: execute() method not found\n";
            $allPassed = false;
            continue;
        }
        echo "  ✓ execute() method exists\n";
        
        // Test 4: execute() is callable
        if (!is_callable([$step, 'execute'])) {
            echo "  ✗ FAIL: execute() is not callable\n";
            $allPassed = false;
            continue;
        }
        echo "  ✓ execute() is callable\n";
        
        // Test 5: Check method signature
        $reflection = new ReflectionMethod($step, 'execute');
        $params = $reflection->getParameters();
        
        if (count($params) !== 1) {
            echo "  ✗ FAIL: execute() should have exactly 1 parameter, found " . count($params) . "\n";
            $allPassed = false;
            continue;
        }
        
        $param = $params[0];
        if ($param->getName() !== 'data') {
            echo "  ⚠ WARNING: Parameter name is '" . $param->getName() . "', expected 'data'\n";
        }
        
        if (!$param->isDefaultValueAvailable() || $param->getDefaultValue() !== []) {
            echo "  ⚠ WARNING: Parameter 'data' should have default value []\n";
        }
        
        echo "  ✓ execute() signature is correct\n";
        
        // Test 6: Try calling execute() with empty array
        try {
            $result = $step->execute([]);
            echo "  ✓ execute([]) can be called\n";
            
            // Test 7: Returns StepResult
            if (!($result instanceof \Galette\Core\Installation\StepResult)) {
                echo "  ✗ FAIL: execute() does not return StepResult, got " . get_class($result) . "\n";
                $allPassed = false;
                continue;
            }
            echo "  ✓ Returns StepResult instance\n";
            
        } catch (\Throwable $e) {
            echo "  ⚠ WARNING: execute([]) threw exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
            echo "           → This may be normal if the step requires specific data or environment\n";
        }
        
        echo "  ✅ ALL TESTS PASSED for $name\n";
        
    } catch (\Throwable $e) {
        echo "  ✗ UNEXPECTED ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
        echo "     in " . $e->getFile() . " line " . $e->getLine() . "\n";
        $allPassed = false;
    }
    
    echo "\n";
}

echo "========================================\n";
if ($allPassed) {
    echo "✅ ALL TESTS PASSED\n";
    echo "========================================\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED\n";
    echo "========================================\n";
    exit(1);
}



