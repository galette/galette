<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * i18n functions
 *
 * PHP version 5
 *
 * Copyright © 2003-2014 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 * @author    Frédéric Jacquot <unknown@unknow.com>
 * @author    Georges Khaznadar (i18n using gettext) <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2003-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

if (!defined('GALETTE_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Galette\Core\L10n;

$i18n->updateEnv();
global $language;
$language = $i18n->getLongID();

/**
 * Translate a string, or return original one
 *
 * @param string  $string The string to translate
 * @param string  $domain Translation domain. Default to false (will take default domain)
 * @param boolean $nt     Indicate not translated strings; defaults to true
 *
 * @return string
 */
function _T($string, $domain = 'galette', $nt = true)
{
    global $language, $installer, $translator, $l10n;

    if (empty($string)) {
        Analog::log(
            'Cannot translate empty strings..',
            Analog::INFO
        );
        return $string;
    }

    if (strpos($domain, 'route') !== false) {
        Analog::log(
            'Routes are no longer translated, return string.',
            Analog::DEBUG
        );
        return $string;
    }

    if ($translator->translationExists($string, $domain)) {
        return $translator->translate($string, $domain);
    }

    $trans = false;
    if (!isset($installer) || $installer !== true) {
        $trans = $l10n->getDynamicTranslation(
            $string,
            $language
        );
    }

    if (!$trans) {
        $trans = $string;

        if (GALETTE_MODE == 'DEV' && $nt === true) {
            $trans .= ' (not translated)';
        }
    }
    return $trans;
}

/**
 * Translate a string, without displaying not translated
 *
 * @param string $string The string to translate
 * @param string $domain Translation domain. Default to false (will take default domain)
 *
 * @return string
 */
function __($string, $domain = 'galette')
{
    return _T($string, $domain, false);
}

/**********************************************
* some constant strings found in the database *
**********************************************/
/** TODO: these string should be not be handled here */
$foo = _T("Realization:");
$foo = _T("Graphics:");
$foo = _T("Publisher:");
$foo = _T("President");
$foo = _T("Vice-president");
$foo = _T("Treasurer");
$foo = _T("Vice-treasurer");
$foo = _T("Secretary");
$foo = _T("Vice-secretary");
$foo = _T("Active member");
$foo = _T("Benefactor member");
$foo = _T("Founder member");
$foo = _T("Old-timer");
$foo = _T("Legal entity");
$foo = _T("Non-member");
$foo = _T("Reduced annual contribution");
$foo = _T("Company cotisation");
$foo = _T("Donation in kind");
$foo = _T("Donation in money");
$foo = _T("Partnership");
$foo = _T("french");
$foo = _T("english");
$foo = _T("spanish");
$foo = _T("annual fee");
$foo = _T("annual fee (to be paid)");
$foo = _T("company fee");
$foo = _T("donation in kind");
$foo = _T("donation in money");
$foo = _T("partnership");
$foo = _T("reduced annual fee");
$foo = _T("Identity");
$foo = _T("Galette-related data");
$foo = _T("Contact information");
$foo = _T("Mr.");
$foo = _T("Mrs.");
$foo = _T("Miss");
$foo = _T("Identity:");
$foo = _T("Galette-related data:");
$foo = _T("Contact information:");
$foo = _T("Society");
