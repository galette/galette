<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Smarty main initialisation
 *
 * PHP version 5
 *
 * Copyright © 2006-2013 The Galette Team
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
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Georges Khaznadar (i18n using gettext) <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2006-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.63
 */

if (!defined('GALETTE_ROOT')) {
       die("Sorry. You can't access directly to this file");
}

if ( !defined('GALETTE_TPL_SUBDIR') ) {
    define('GALETTE_TPL_SUBDIR', 'templates/' . $preferences->pref_theme . '/');
}
$tpl = new Galette\Core\Smarty($plugins, $i18n, $preferences, $logo, $login, $session);
$tpl->muteExpectedErrors();

$tpl->registerClass('GaletteMail', '\Galette\Core\GaletteMail');

/**
* Return member name. Smarty cannot directly use static functions
*
* @param array $params Parameters
*
* @return Adherent::getSName
* @see Adherent::getSName
*/
function getMemberName($params)
{
    extract($params);
    return Galette\Entity\Adherent::getSName($id);
}
$tpl->registerPlugin(
    'function',
    'memberName',
    'getMemberName'
);

$s = new Galette\Entity\Status();
$statuses_list = $s->getList();

/**
 * Return status label
 *
 * @param array $params Parameters
 *
 * @return string
 */
function getStatusLabel($params)
{
    extract($params);
    global $statuses_list;
    return $statuses_list[$id];
}
$tpl->registerPlugin(
    'function',
    'statusLabel',
    'getStatusLabel'
);

