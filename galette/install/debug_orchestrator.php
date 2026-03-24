<?php

/**
 * Debug script for installation orchestrator
 * 
 * This script helps debug the auto-advancement system
 * Usage: Add this at the top of installer.php temporarily
 */

// Enable error display
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Log to file
$debug_log = GALETTE_ROOT . 'galette/data/logs/installer_debug.log';
function debug_log($message) {
    global $debug_log;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debug_log, "[$timestamp] $message\n", FILE_APPEND);
}

debug_log("========== INSTALLER DEBUG START ==========");
debug_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
debug_log("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
debug_log("POST data: " . json_encode($_POST));
debug_log("GET data: " . json_encode($_GET));

// Check if orchestrator is loaded
if (function_exists('shouldUseNewSystem')) {
    debug_log("✓ Orchestrator loaded");
    
    if (isset($install)) {
        debug_log("Current step check:");
        debug_log("  - isCheckStep: " . ($install->isCheckStep() ? 'YES' : 'NO'));
        debug_log("  - isDbCheckStep: " . ($install->isDbCheckStep() ? 'YES' : 'NO'));
        debug_log("  - isDbinstallStep: " . ($install->isDbinstallStep() ? 'YES' : 'NO'));
        
        $useNew = shouldUseNewSystem($install);
        debug_log("  - shouldUseNewSystem: " . ($useNew ? 'YES' : 'NO'));
        
        if ($useNew) {
            $className = getStepClassName($install);
            debug_log("  - Step class: " . ($className ?? 'NULL'));
            
            // Test Step instantiation
            if ($className !== null && class_exists($className)) {
                try {
                    debug_log("  - Testing Step instantiation...");
                    $testStep = new $className($install);
                    debug_log("  ✓ Step instantiation successful");
                    
                    // Test execute method exists
                    if (method_exists($testStep, 'execute')) {
                        debug_log("  ✓ execute() method exists");
                    } else {
                        debug_log("  ✗ execute() method NOT found");
                    }
                } catch (\ArgumentCountError $e) {
                    debug_log("  ✗ ArgumentCountError: " . $e->getMessage());
                    debug_log("  → Step constructor requires different arguments");
                } catch (\TypeError $e) {
                    debug_log("  ✗ TypeError: " . $e->getMessage());
                } catch (\Throwable $e) {
                    debug_log("  ✗ Exception: " . get_class($e) . ": " . $e->getMessage());
                }
            }
        }
    } else {
        debug_log("✗ $install not available yet");
    }
} else {
    debug_log("✗ Orchestrator NOT loaded");
}

// Check if StepResult is available
if (isset($stepResult)) {
    debug_log("StepResult:");
    debug_log("  - Type: " . get_class($stepResult));
    debug_log("  - requiresDisplay: " . ($stepResult->requiresDisplay() ? 'YES' : 'NO'));
    debug_log("  - isSuccess: " . ($stepResult->isSuccess() ? 'YES' : 'NO'));
    debug_log("  - Messages: " . json_encode($stepResult->getMessages()));
} else {
    debug_log("StepResult: NULL");
}

debug_log("========== INSTALLER DEBUG END ==========");


