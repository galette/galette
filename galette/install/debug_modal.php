<?php

/**
 * Debug script for DatabaseInstallStep modal issue
 * 
 * Add this at the top of installer.php to enable detailed debugging
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Log modal rendering
function debug_modal_log(string $message): void
{
    $logFile = __DIR__ . '/../data/logs/modal_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// JavaScript debug helper
function inject_modal_debug_js(): void
{
    ?>
    <script>
        console.log('[MODAL DEBUG] Script loaded');
        
        // Check jQuery
        if (typeof jQuery === 'undefined') {
            console.error('[MODAL DEBUG] jQuery NOT loaded!');
        } else {
            console.log('[MODAL DEBUG] jQuery loaded:', jQuery.fn.jquery);
        }
        
        // Check Semantic UI modal
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal === 'undefined') {
            console.error('[MODAL DEBUG] Semantic UI modal NOT loaded!');
        } else {
            console.log('[MODAL DEBUG] Semantic UI modal available');
        }
        
        // Monitor DOM for modal element
        $(document).ready(function() {
            console.log('[MODAL DEBUG] DOM ready');
            
            var modal = $('#db-install-report');
            console.log('[MODAL DEBUG] Modal element found:', modal.length);
            
            if (modal.length > 0) {
                console.log('[MODAL DEBUG] Modal HTML:', modal[0].outerHTML.substring(0, 200));
                
                // Try to show modal manually for debugging
                setTimeout(function() {
                    console.log('[MODAL DEBUG] Attempting to show modal...');
                    try {
                        modal.modal('show');
                        console.log('[MODAL DEBUG] modal.modal("show") called successfully');
                    } catch (e) {
                        console.error('[MODAL DEBUG] Error showing modal:', e);
                    }
                }, 500);
            } else {
                console.error('[MODAL DEBUG] Modal element NOT found in DOM!');
                console.log('[MODAL DEBUG] Available modals:', $('.ui.modal').length);
            }
        });
    </script>
    <?php
}

