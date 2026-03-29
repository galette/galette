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

/*
 * Test bootstrap
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */


require_once __DIR__ . '/test_env.inc.php';
require_once __DIR__ . '/init_test_data.php';

//TODO: maybe is there a better way to do
$logfile = 'galette_tests'; //phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used in galette.inc.php
require_once GALETTE_BASE_PATH . 'includes/galette.inc.php';

//disabled news and disabled plugins
touch(GALETTE_PLUGINS_DATA_PATH . '/plugin_plugin-news_disabled');
touch(GALETTE_PLUGINS_DATA_PATH . '/plugin_plugin-disabled_disabled');

$session_name = 'galette_tests';
$session = new \RKA\SessionMiddleware([
    'name'      => $session_name,
    'lifetime'  => 0
]);
if (session_status() === PHP_SESSION_NONE) {
    $session->start();
}

if (!defined('_CURRENT_THEME_PATH')) {
    define(
        '_CURRENT_THEME_PATH',
        GALETTE_THEMES_PATH . 'default/'
    );
}

require_once GALETTE_BASE_PATH . 'includes/main.inc.php';
//Globals... :(
global $preferences, $emitter, $zdb;
//phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- globals \o/
/** @var \DI\Container $container */
$zdb = $container->get(\Galette\Core\Db::class);
$preferences = $container->get(\Galette\Core\Preferences::class);
$emitter = $container->get(\League\Event\EventDispatcher::class);
//phpcs:enable
/** @var \Galette\Core\I18n $i18n */
$i18n->changeLanguage('en_US');

/** @var string $testenv - declared in test_env.inc.php */
if (
    $testenv !== 'UPDATE'
    && $testenv !== 'FAIL'
) {
    $fc = $container->get(\Galette\Entity\FieldsConfig::class);
    $categorized_fields = $fc->getCategorizedFields();
    foreach ($categorized_fields as &$fieldset) {
        foreach ($fieldset as &$field) {
            if ($field['field_id'] == 'fingerprint') {
                $field['visible'] = \Galette\Entity\FieldsConfig::ALL; //make sure fingerprint field is visible
            }
        }
    }
    $fc->setFields($categorized_fields);
}
