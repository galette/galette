<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Smarty main initialisation
 *
 * PHP version 5
 *
 * Copyright © 2006-2011 The Galette Team
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
 * @copyright 2006-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.63
 */

require 'Smarty.class.php';

$tpl = new Smarty;
$template_subdir = 'templates/' . $preferences->pref_theme . '/';
$tpl->plugins_dir[] = WEB_ROOT . 'includes/smarty_plugins';
$tpl->template_dir = WEB_ROOT . $template_subdir;
$tpl->compile_dir = GALETTE_COMPILE_DIR;
$tpl->cache_dir = GALETTE_CACHE_DIR;
$tpl->config_dir = WEB_ROOT . 'config/';

$tpl->assign('login', $login);
$tpl->assign('logo', $logo);
$tpl->assign('template_subdir', $base_path . $template_subdir);
foreach ( $plugins->getTplAssignments() as $k=>$v ) {
    $tpl->assign($k, $v);
}
$tpl->assign('headers', $plugins->getTplHeaders());
$tpl->assign('plugin_actions', $plugins->getTplAdhActions());
$tpl->assign('plugin_detailled_actions', $plugins->getTplAdhDetailledActions());
$tpl->assign('jquery_dir', $base_path . 'includes/jquery/');
$tpl->assign('jquery_version', JQUERY_VERSION);
$tpl->assign('jquery_ui_version', JQUERY_UI_VERSION);
$tpl->assign('jquery_markitup_version', JQUERY_MARKITUP_VERSION);
$tpl->assign('scripts_dir', $base_path . 'includes/');
$tpl->assign('PAGENAME', basename($_SERVER['SCRIPT_NAME']));
$tpl->assign('galette_base_path', $base_path);
/** FIXME: on certains pages PHP notice that GALETTE_VERSION does not exists
although it appears correctly*/
$tpl->assign('GALETTE_VERSION', GALETTE_VERSION);
$tpl->assign('GALETTE_MODE', GALETTE_MODE);
/** galette_lang should be removed and languages used instead */
$tpl->assign('galette_lang', $i18n->getAbbrev());
$tpl->assign('languages', $i18n->getList());
$tpl->assign('plugins', $plugins);
$tpl->assign('preferences', $preferences);
$tpl->assign('pref_slogan', $preferences->pref_slogan);
$tpl->assign('pref_theme', $preferences->pref_theme);
$tpl->assign('pref_editor_enabled', $preferences->pref_editor_enabled);
$tpl->assign('pref_mail_method', $preferences->pref_mail_method);
if ( isset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['mailing']) ) {
    $tpl->assign('existing_mailing', true);
}
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
    return Adherent::getSName($id);
}
$tpl->register_function('memberName', 'getMemberName');
?>
