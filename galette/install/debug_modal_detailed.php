<?php

/**
 * Debug DatabaseInstallStep modal issue
 * Add at the beginning of installer.php after bootstrap
 */

// Log file
$debug_log = GALETTE_ROOT . 'data/logs/modal_debug.log';

// Helper to log
function modal_debug_log($message) {
    global $debug_log;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debug_log, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// Clear log at start
if (isset($_GET['raz'])) {
    @unlink($debug_log);
}

modal_debug_log("=== INSTALLER.PHP DEBUG START ===");
modal_debug_log("Current step: " . ($install->getStep() ?? 'none'));
modal_debug_log("Is DB install step: " . ($install->isDbinstallStep() ? 'YES' : 'NO'));
modal_debug_log("Is DB upgrade step: " . ($install->isDbUpgradeStep() ? 'YES' : 'NO'));
modal_debug_log("Should use new system: " . (shouldUseNewSystem($install) ? 'YES' : 'NO'));

if (shouldUseNewSystem($install)) {
    $stepClassName = getStepClassName($install);
    modal_debug_log("Step class name: " . ($stepClassName ?? 'NULL'));
    
    if ($stepClassName !== null && isset($stepResult)) {
        modal_debug_log("StepResult exists: YES");
        modal_debug_log("StepResult requiresDisplay: " . ($stepResult->requiresDisplay() ? 'true' : 'false'));
        
        if (!$stepResult->requiresDisplay()) {
            $stepData = $stepResult->getData();
            modal_debug_log("StepData keys: " . implode(', ', array_keys($stepData)));
            modal_debug_log("Has show_report_modal flag: " . (isset($stepData['show_report_modal']) ? 'YES' : 'NO'));
            
            if (isset($stepData['show_report_modal'])) {
                modal_debug_log("show_report_modal value: " . ($stepData['show_report_modal'] ? 'true' : 'false'));
            }
        }
    } else {
        modal_debug_log("StepResult: " . (isset($stepResult) ? 'exists but no className' : 'NOT SET'));
    }
}

// Inject JavaScript debug
if ($install->isDbinstallStep() || $install->isDbUpgradeStep()) {
    ?>
    <script>
        console.log('[MODAL DEBUG] DatabaseInstallStep detected');
        console.log('[MODAL DEBUG] jQuery loaded:', typeof jQuery !== 'undefined');
        console.log('[MODAL DEBUG] Modal element exists:', $('#db-install-report').length > 0);
        
        if ($('#db-install-report').length > 0) {
            console.log('[MODAL DEBUG] Modal HTML found:', $('#db-install-report')[0].outerHTML.substring(0, 100));
        } else {
            console.log('[MODAL DEBUG] Modal element NOT found in DOM');
            console.log('[MODAL DEBUG] All .ui.modal elements:', $('.ui.modal').length);
            $('.ui.modal').each(function(i) {
                console.log('[MODAL DEBUG] Modal ' + i + ' id:', this.id);
            });
        }
    </script>
    <?php
}

