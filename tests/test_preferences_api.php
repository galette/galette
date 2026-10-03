<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Test API for managing preferences in E2E tests
 *
 * This file provides endpoints to dynamically modify Galette preferences
 * during Playwright tests without requiring UI interactions.
 *
 * Available actions:
 * - enable_public_pages: Enable public pages with specified visibility
 * - disable_public_pages: Disable public pages
 * - set_public_page_visibility: Set visibility for a specific public page
 * - restore_default_public_pages: Restore default public pages configuration
 * - get_public_pages_config: Get current public pages configuration
 * - configure_mail: Configure SMTP mailing (used with a local mail catcher)
 * - reset_mail: Disable mailing and reset batching/throttling preferences
 * - set_two_factor_mode: Set the second factor policy
 * - reset_two_factor: Clear every second factor, so a run starts from a known
 *   state even after a previous one failed halfway through
 */

use Galette\Core\Db;
use Galette\Core\GaletteMail;
use Galette\Core\Preferences;

use function Safe\file_get_contents;
use function Safe\json_decode;
use function Safe\json_encode;

// Define constants for Galette
if (!defined('GALETTE_ROOT')) {
    define('GALETTE_ROOT', __DIR__ . '/../galette/'); //@phpstan-ignore theCodingMachineSafe.function
}
if (!defined('GALETTE_BASE_PATH')) {
    define('GALETTE_BASE_PATH', '../../'); //@phpstan-ignore theCodingMachineSafe.function
}

//This script deliberately skips includes/galette.inc.php, so the constants that
//file defaults have to be stated here: Db logging asks Galette whether debug is
//on before running the very first query.
if (!defined('GALETTE_DEBUG')) {
    define('GALETTE_DEBUG', value: false); //@phpstan-ignore theCodingMachineSafe.function
}

// Load Galette config and autoloader only
require_once GALETTE_ROOT . 'includes/sys_config/versions.inc.php';
require_once GALETTE_ROOT . 'includes/sys_config/paths.inc.php';
require_once GALETTE_CONFIG_PATH . 'config.inc.php';
require_once GALETTE_ROOT . 'vendor/autoload.php';

//this bootstrap skips includes/galette.inc.php, which is where the flag is
//normally settled; Db::log() reads it on every query
if (!defined('GALETTE_DEBUG')) {
    define('GALETTE_DEBUG', value: false); //@phpstan-ignore theCodingMachineSafe.function
}

// Manually instantiate required objects
$zdb = new Db();
$preferences = new Preferences($zdb);

header('Content-Type: application/json');

// Security check: only accessible via test router
// The test router (tests/router_e2e.php) is only used in E2E test context
// Additional security could be added here if needed (e.g., checking for specific header)

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), associative: true);
        $action = $input['action'] ?? '';

        switch ($action) {
            case 'enable_public_pages':
                $visibility = $input['visibility'] ?? Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED;
                $preferences->pref_bool_publicpages = true;
                $preferences->pref_publicpages_visibility_generic = $visibility;
                $preferences->pref_publicpages_visibility_memberslist = $visibility;
                $preferences->pref_publicpages_visibility_membersgallery = $visibility;
                $preferences->pref_publicpages_visibility_stafflist = $visibility;
                $preferences->pref_publicpages_visibility_staffgallery = $visibility;
                $preferences->pref_publicpages_visibility_documents = $visibility;
                $preferences->store();
                echo json_encode(['success' => true, 'message' => 'Public pages enabled']);
                break;

            case 'disable_public_pages':
                $preferences->pref_bool_publicpages = false;
                $preferences->store();
                echo json_encode(['success' => true, 'message' => 'Public pages disabled']);
                break;

            case 'set_public_page_visibility':
                $pageName = $input['page_name'] ?? '';
                $visibility = $input['visibility'] ?? Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED;

                if (empty($pageName)) {
                    throw new \RuntimeException('page_name is required');
                }

                $preferences->$pageName = $visibility;
                $preferences->store();
                echo json_encode(['success' => true, 'message' => "Visibility set for {$pageName}"]);
                break;

            case 'restore_default_public_pages':
                $preferences->pref_bool_publicpages = true;
                $preferences->pref_publicpages_visibility_generic = Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED;
                $preferences->pref_publicpages_visibility_memberslist = Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED;
                $preferences->pref_publicpages_visibility_membersgallery = Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED;
                $preferences->pref_publicpages_visibility_stafflist = Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED;
                $preferences->pref_publicpages_visibility_staffgallery = Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED;
                $preferences->pref_publicpages_visibility_documents = Preferences::PUBLIC_PAGES_VISIBILITY_RESTRICTED;
                $preferences->store();
                echo json_encode(['success' => true, 'message' => 'Default public pages configuration restored']);
                break;

            case 'set_two_factor_mode':
                $preferences->pref_2fa_mode = (int)($input['mode'] ?? 0);
                $preferences->store();
                echo json_encode(['success' => true, 'message' => 'Second factor mode set']);
                break;

            case 'reset_two_factor':
                //the super administrator keeps its own in the preferences
                $preferences->pref_2fa_superadmin_secret = '';
                $preferences->pref_2fa_superadmin_enabled = false;
                $preferences->pref_2fa_superadmin_timeslice = 0;
                $preferences->pref_2fa_mode = 0;
                $preferences->store();

                //members have rows, and so do the throttling counters: a run
                //that locked an account out must not poison the next one
                foreach (['twofactor_codes', 'twofactor', 'auth_attempts'] as $table) {
                    $delete = $zdb->delete($table);
                    $zdb->execute($delete);
                }
                echo json_encode(['success' => true, 'message' => 'Second factors cleared']);
                break;

            case 'configure_mail':
                //point Galette to a local SMTP catcher (e.g. Mailpit) and set
                //batching/throttling so mass mailings go through the queue
                $preferences->pref_mail_method = GaletteMail::METHOD_SMTP;
                $preferences->pref_mail_smtp_host = $input['host'] ?? '127.0.0.1';
                $preferences->pref_mail_smtp_port = $input['port'] ?? 1025;
                $preferences->pref_mail_smtp_auth = false;
                $preferences->pref_mail_smtp_secure = false;
                $preferences->pref_mail_smtp_keepalive = true;
                $preferences->pref_mail_batch_size = $input['batch_size'] ?? 2;
                $preferences->pref_mail_batch_delay = $input['batch_delay'] ?? 0;
                $preferences->pref_mail_hourly_limit = $input['hourly_limit'] ?? 0;
                $preferences->pref_mail_daily_limit = $input['daily_limit'] ?? 1000;
                $preferences->store();
                echo json_encode(['success' => true, 'message' => 'Mail configured']);
                break;

            case 'reset_mail':
                $preferences->pref_mail_method = GaletteMail::METHOD_DISABLED;
                $preferences->pref_mail_batch_size = 0;
                $preferences->pref_mail_batch_delay = 0;
                $preferences->pref_mail_hourly_limit = 0;
                $preferences->pref_mail_daily_limit = 0;
                $preferences->store();
                echo json_encode(['success' => true, 'message' => 'Mail reset']);
                break;

            default:
                throw new \RuntimeException("Unknown action: {$action}");
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';

        if ($action === 'get_public_pages_config') {
            echo json_encode([
                'enabled' => $preferences->pref_bool_publicpages,
                'generic' => $preferences->pref_publicpages_visibility_generic,
                'memberslist' => $preferences->pref_publicpages_visibility_memberslist,
                'membersgallery' => $preferences->pref_publicpages_visibility_membersgallery,
                'stafflist' => $preferences->pref_publicpages_visibility_stafflist,
                'staffgallery' => $preferences->pref_publicpages_visibility_staffgallery,
                'documents' => $preferences->pref_publicpages_visibility_documents,
            ]);
        } else {
            throw new \RuntimeException("Unknown GET action: {$action}");
        }
    } else {
        throw new \RuntimeException('Only POST and GET methods are supported');
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}
