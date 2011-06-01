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

// smarty-light declaration
$galetteSmartyPath = WEB_ROOT . 'includes/Smarty-' . SMARTY_VERSION . '/';
if ( file_exists('/usr/share/Smarty/Smarty.class.php') ) {
    $galetteSmartyPath = '/usr/share/Smarty/';
}
if ( file_exists('/usr/share/php/Smarty/Smarty.class.php') ) {
    $galetteSmartyPath = '/usr/share/php/Smarty/';
}

require $galetteSmartyPath . 'Smarty.class.php';

$tpl = new Smarty;
$template_subdir = 'templates/' . $preferences->pref_theme . '/';
$tpl->plugins_dir[] = WEB_ROOT . 'includes/smarty_plugins';
$tpl->template_dir = WEB_ROOT . $template_subdir;
$tpl->compile_dir = WEB_ROOT . 'templates_c/';
$tpl->cache_dir = WEB_ROOT . 'cache/';
$tpl->config_dir = WEB_ROOT . 'config/';

$tpl->assign('login', $login);
$tpl->assign('logo', $logo);
$tpl->assign('template_subdir', $base_path . $template_subdir);
foreach ( $plugins->getTplAssignments() as $k=>$v ) {
    $tpl->assign($k, $v);
}
$tpl->assign('headers', $plugins->getTplHeaders());
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
/** galette_lang should be removed and languages used instead */
$tpl->assign('galette_lang', $i18n->getAbbrev());
$tpl->assign('languages', $i18n->getList());
$tpl->assign('plugins', $plugins);
$tpl->assign('pref_slogan', $preferences->pref_slogan);
$tpl->assign('pref_theme', $preferences->pref_theme);
$tpl->assign('pref_editor_enabled', $preferences->pref_editor_enabled);
$tpl->assign('pref_mail_method', $preferences->pref_mail_method);
?>
