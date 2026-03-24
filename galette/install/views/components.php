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
 * Reusable view components for installer
 *
 * This file contains helper functions to render common UI components
 * used throughout the installation process. All functions output HTML directly.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

/**
 * Render validation list with check/cross icons
 *
 * @param array<array{message: string, res: bool, debug?: string}> $validations List of validation items
 * @param \Galette\Core\Install                                    $install     Install instance
 */
function renderValidationList(array $validations, \Galette\Core\Install $install): void
{
    echo '<ul class="leaders">';
    foreach ($validations as $item) {
        echo '<li>';
        echo '<span>' . htmlspecialchars($item['message']) . '</span>';
        echo '<span>' . $install->getValidationImage($item['res']) . '</span>';
        echo '</li>';
        if (isset($item['debug']) && !$item['res']) {
            echo '<li class="debug-info"><small class="ui red text">' . htmlspecialchars($item['debug']) . '</small></li>';
        }
    }
    echo '</ul>';
}

/**
 * Render message box (Semantic UI styled)
 *
 * @param string               $type     Type: success, error, warning, info
 * @param string|array<string> $messages Message(s) to display
 * @param bool                 $icon     Show icon (default: true)
 */
function renderMessageBox(string $type, string|array $messages, bool $icon = true): void
{
    $class = match ($type) {
        'success' => 'green',
        'error' => 'red',
        'warning' => 'orange',
        default => 'blue'
    };

    $iconName = match ($type) {
        'success' => 'check circle',
        'error' => 'times circle',
        'warning' => 'exclamation triangle',
        default => 'info circle'
    };

    echo '<div class="ui ' . $class . ' message' . ($icon ? ' icon' : '') . '">';
    if ($icon) {
        echo '<i class="' . $iconName . ' icon" aria-hidden="true"></i>';
    }
    echo '<div class="content">';

    if (is_array($messages)) {
        if (count($messages) === 1) {
            echo '<p>' . htmlspecialchars($messages[0]) . '</p>';
        } else {
            echo '<ul class="list">';
            foreach ($messages as $msg) {
                echo '<li>' . htmlspecialchars($msg) . '</li>';
            }
            echo '</ul>';
        }
    } else {
        echo '<p>' . htmlspecialchars($messages) . '</p>';
    }

    echo '</div></div>';
}

/**
 * Render modal for database installation report
 *
 * The modal shows detailed SQL execution results and is shown automatically
 * when the page loads (via JavaScript).
 *
 * @param array<array{message: string, res: bool, query?: string, debug?: string}> $report  Report items
 * @param \Galette\Core\Install                                                    $install Install instance
 * @param \Galette\Core\I18n                                                       $i18n    I18n instance
 * @param bool                                                                     $success Overall success status
 */
function renderDbReportModal(array $report, \Galette\Core\Install $install, \Galette\Core\I18n $i18n, bool $success = true): void
{
    $modalId = 'db-install-report';
    $title = $install->isInstall() ? _T("Database installation report") : _T("Database upgrade report");
    $successMsg = $install->isInstall() ? _T("Database has been installed :)") : _T("Database has been upgraded :)");
    $failMsg = $install->isInstall() ? _T("Database has not been installed!") : _T("Database has not been upgraded!");
    ?>
    <div class="ui modal" id="<?php echo $modalId; ?>">
        <div class="header">
            <i class="database icon"></i>
            <?php echo $title; ?>
        </div>
        <div class="scrolling content">
            <?php if ($success) { ?>
                <div class="ui green message">
                    <i class="check circle icon"></i>
                    <?php echo $successMsg; ?>
                </div>
            <?php } else { ?>
                <div class="ui red message">
                    <i class="times circle icon"></i>
                    <?php echo $failMsg; ?>
                </div>
            <?php } ?>

            <?php if (count($report) > 0) { ?>
                <h4><?php echo _T("Execution details:"); ?></h4>
                <?php renderValidationList($report, $install); ?>
            <?php } ?>
        </div>
        <div class="actions">
            <div class="ui positive right labeled icon button" id="modal-ok-btn">
                <?php echo _T("OK"); ?>
                <i class="checkmark icon"></i>
            </div>
        </div>
    </div>
    <script>
        (function() {
            // Use IIFE to avoid conflicts and ensure execution
            var showModal = function() {
                var modal = $('#<?php echo $modalId; ?>');
                
                if (modal.length === 0) {
                    console.error('Modal element not found: <?php echo $modalId; ?>');
                    return;
                }
                
                console.log('Initializing modal...');
                
                modal.modal({
                    closable: <?php echo $success ? 'true' : 'false'; ?>,
                    onHidden: function() {
                        <?php if ($success) { ?>
                        // Auto-submit form to proceed to next step
                        var form = document.getElementById('install-continue-form');
                        if (form) {
                            console.log('Submitting form to continue...');
                            form.submit();
                        }
                        <?php } ?>
                    }
                });
                
                // Show modal immediately
                console.log('Showing modal...');
                modal.modal('show');
            };
            
            // Close button handler
            $('#modal-ok-btn').on('click', function() {
                console.log('OK button clicked');
                $('#<?php echo $modalId; ?>').modal('hide');
            });
            
            // Execute immediately if jQuery is ready, otherwise wait
            if (typeof jQuery !== 'undefined') {
                if (document.readyState === 'complete' || document.readyState === 'interactive') {
                    // DOM already loaded
                    setTimeout(showModal, 100);
                } else {
                    // Wait for DOM to load
                    $(document).ready(showModal);
                }
            } else {
                console.error('jQuery not loaded!');
            }
        })();
    </script>
    <?php
}

/**
 * Render step progress indicator (sidebar)
 *
 * Shows all installation steps with visual indicators for:
 * - Current step (active)
 * - Completed steps (green checkmark)
 * - Future steps (disabled)
 *
 * @param \Galette\Core\Install $install Install instance
 * @param \Galette\Core\I18n    $i18n    I18n instance
 */
function renderStepProgress(\Galette\Core\Install $install, \Galette\Core\I18n $i18n): void
{
    $steps = [
        [
            'constant' => \Galette\Core\Install::STEP_CHECK,
            'title' => _T("Checks"),
            'icon' => 'tasks',
            'method' => 'isCheckStep'
        ],
        [
            'constant' => \Galette\Core\Install::STEP_TYPE,
            'title' => _T("Installation mode"),
            'icon' => 'question',
            'method' => 'isTypeStep'
        ],
        [
            'constant' => \Galette\Core\Install::STEP_DB,
            'title' => _T("Database"),
            'icon' => 'database',
            'method' => 'isDbStep'
        ],
        [
            'constant' => \Galette\Core\Install::STEP_DB_CHECKS,
            'title' => _T("Database access and permissions"),
            'icon' => 'key',
            'method' => 'isDbCheckStep'
        ],
    ];

    // Add version selection step for upgrades
    if ($install->isUpgrade()) {
        $steps[] = [
            'constant' => \Galette\Core\Install::STEP_VERSION,
            'title' => _T("Version selection"),
            'icon' => 'tag',
            'method' => 'isVersionSelectionStep'
        ];
        $steps[] = [
            'constant' => \Galette\Core\Install::STEP_DB_UPGRADE,
            'title' => _T("Database upgrade"),
            'icon' => 'sync alt',
            'method' => 'isDbUpgradeStep'
        ];
    } else {
        $steps[] = [
            'constant' => \Galette\Core\Install::STEP_DB_INSTALL,
            'title' => _T("Database installation"),
            'icon' => 'spinner',
            'method' => 'isDbinstallStep'
        ];
    }

    // Add admin step for fresh installs
    if (!$install->isUpgrade()) {
        $steps[] = [
            'constant' => \Galette\Core\Install::STEP_ADMIN,
            'title' => _T("Admin parameters"),
            'icon' => 'user',
            'method' => 'isAdminStep'
        ];
    }

    // Add common final steps
    $steps[] = [
        'constant' => \Galette\Core\Install::STEP_TELEMETRY,
        'title' => _T("Telemetry"),
        'icon' => 'chart bar',
        'method' => 'isTelemetryStep'
    ];
    $steps[] = [
        'constant' => \Galette\Core\Install::STEP_GALETTE_INIT,
        'title' => _T("Galette initialization"),
        'icon' => 'cogs',
        'method' => 'isGaletteInitStep'
    ];
    $steps[] = [
        'constant' => \Galette\Core\Install::STEP_END,
        'title' => _T("End!"),
        'icon' => 'flag checkered',
        'method' => 'isEndStep'
    ];

    echo '<div class="ui stackable mini vertical steps fluid">';
    foreach ($steps as $step) {
        $isActive = $install->{$step['method']}();
        $isPassed = $install->isStepPassed($step['constant']);
        $isDisabled = !$isPassed && !$isActive;

        $classes = 'step';
        if ($isActive) {
            $classes .= ' active';
        }
        if ($isDisabled) {
            $classes .= ' disabled';
        }

        echo '<div class="' . $classes . '">';
        echo '<i class="' . $step['icon'] . ' icon' . ($isPassed ? ' green' : '') . '" aria-hidden="true"></i>';
        echo '<div class="content">';
        echo '<div class="title">' . $step['title'] . '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

/**
 * Render form navigation buttons (Next/Back/Retry)
 *
 * @param bool                  $canAdvance   Can proceed to next step
 * @param bool                  $canGoBack    Can return to previous step
 * @param bool                  $showRetry    Show retry button instead of next
 * @param \Galette\Core\I18n    $i18n         I18n instance
 * @param array<string, string> $hiddenInputs Additional hidden inputs to include
 */
function renderFormNavigation(
    bool $canAdvance,
    bool $canGoBack,
    bool $showRetry,
    \Galette\Core\I18n $i18n,
    array $hiddenInputs = []
): void {
    echo '<div class="ui section divider"></div>';
    echo '<form action="installer.php" method="POST" class="ui form">';
    echo '<div class="ui mobile reversed tablet reversed computer reversed equal width grid">';
    echo '<div class="right aligned column">';

    if ($showRetry) {
        echo '<button type="submit" class="ui right labeled icon button">';
        echo '<i class="redo alternate double ' . ($i18n->isRtl() ? 'left' : 'right') . ' icon" aria-hidden="true"></i> ';
        echo _T("Retry");
        echo '</button>';
    } else {
        echo '<button type="submit" class="ui right labeled primary icon button"';
        echo $canAdvance ? '' : ' disabled="disabled"';
        echo '><i class="angle double ' . ($i18n->isRtl() ? 'left' : 'right') . ' icon" aria-hidden="true"></i> ';
        echo _T("Next step");
        echo '</button>';

        foreach ($hiddenInputs as $name => $value) {
            echo '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '"/>';
        }
    }

    echo '</div>';

    if ($canGoBack) {
        echo '<div class="column">';
        echo '<button type="submit" id="btnback" name="stepback_btn" formnovalidate class="ui labeled icon button">';
        echo '<i class="angle double ' . ($i18n->isRtl() ? 'right' : 'left') . ' icon" aria-hidden="true"></i> ';
        echo _T("Back");
        echo '</button>';
        echo '</div>';
    }

    echo '</div>';
    echo '</form>';
}

/**
 * Render auto-advance notification
 *
 * Shows a brief notification message before automatically redirecting
 * to the next step.
 *
 * @param string $message Message to display
 * @param int    $delay   Delay in milliseconds before redirect (default: 1500)
 */
function renderAutoAdvanceNotification(string $message, int $delay = 1500): void
{
    ?>
    <div class="ui success message" id="auto-advance-message">
        <i class="check circle icon"></i>
        <div class="content">
            <p><?php echo htmlspecialchars($message); ?></p>
            <div class="ui tiny indicating progress" data-percent="100" id="advance-progress">
                <div class="bar"></div>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            $('#advance-progress').progress({
                duration: <?php echo $delay; ?>,
                total: 100,
                text: {
                    active: '<?php echo _T("Advancing to next step..."); ?>'
                },
                onSuccess: function() {
                    window.location.href = 'installer.php';
                }
            });
            setTimeout(function() {
                window.location.href = 'installer.php';
            }, <?php echo $delay; ?>);
        });
    </script>
    <?php
}
