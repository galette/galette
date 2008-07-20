<?php

// Copyright © 2003 Frédéric Jaqcuot
// Copyright © 2004 Georges Khaznadar (i18n using gettext)
// Copyright © 2007-2008 Johan Cwiklinski
//
// This file is part of Galette (http://galette.tuxfamily.org).
//
// Galette is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Galette is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Galette. If not, see <http://www.gnu.org/licenses/>.

/**
 * Smarty main initialisation
 *
 * @package Galette
 * 
 * @author     Frédéric Jaqcuot
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 */

	// smarty-light declaration
	$galetteSmartyPath = WEB_ROOT . 'includes/Smarty-2.6.19';
	if (file_exists("/usr/share/Smarty/Smarty.class.php")){
        	$galetteSmartyPath = '/usr/share/Smarty/';
	}
	if (file_exists("/usr/share/php/Smarty/Smarty.class.php")){
        	$galetteSmartyPath = '/usr/share/php/Smarty/';
	}
	include($galetteSmartyPath . 'Smarty.class.php');
	$tpl = new Smarty;
	$template_subdir = 'templates/default/';
	$tpl->plugins_dir[] = WEB_ROOT . 'includes/smarty_plugins';
	$tpl->template_dir = WEB_ROOT . $template_subdir;
	$tpl->compile_dir = WEB_ROOT . 'templates_c/';
	$tpl->cache_dir = WEB_ROOT . 'cache/';
	$tpl->config_dir = WEB_ROOT . 'configs/';
	
	$tpl->assign('template_subdir', $base_path . $template_subdir);
	$tpl->assign('jquery_dir', $base_path . 'includes/jquery/');
	$tpl->assign('htmledi_dir', $base_path . 'includes/tiny_mce/');
	$tpl->assign('scripts_dir', $base_path . 'includes/');
	$tpl->assign('PAGENAME', basename($_SERVER['SCRIPT_NAME']));
	/** FIXME: on certains pages PHP notice that GALETTE_VERSION does not exists although it appears correctly*/
	$tpl->assign('GALETTE_VERSION', GALETTE_VERSION);
	$tpl->assign('galette_lang', $i18n->getAbbrev());
	$tpl->assign('pref_slogan', $preferences['pref_slogan']);
	$tpl->assign('pref_editor_enabled', $preferences['pref_editor_enabled']);
?>
