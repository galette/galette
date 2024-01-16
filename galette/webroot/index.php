<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's instanciation and routes
 *
 * PHP version 5
 *
 * Copyright © 2014 The Galette Team
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
 *
 * @category  Main
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     0.8.2dev 2014-11-11
 */

// define relative base path templating can use
if (!defined('GALETTE_BASE_PATH')) {
    define('GALETTE_BASE_PATH', '../');
}

define('GALETTE_ROOT', __DIR__ . '/../');

// check PHP version
require_once GALETTE_ROOT . 'config/versions.inc.php';
if (version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<')) {
    header('location: ' . GALETTE_BASE_PATH . 'compat_test.php');
    die(1);
}

// check PHP modules
require_once GALETTE_ROOT . '/vendor/autoload.php';

$cm = new Galette\Core\CheckModules(false);
$cm->doCheck(false); //do not load with translations!

if (!$cm->isValid()) {
    header('location: ' . GALETTE_BASE_PATH . 'compat_test.php');
    die(1);
}

/** @ignore */
require_once __DIR__ . '/../includes/main.inc.php';
