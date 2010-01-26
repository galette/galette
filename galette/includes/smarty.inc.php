<?php
/* smarty.inc.php - Part of the Galette Project
 *
 * Copyright (c) 2007-2010 Johan Cwiklinski <johan@x-tnd.be>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 * $Id$
 */

	require_once WEB_ROOT . '/config/versions.inc.php';
	// smarty-light declaration
	include(WEB_ROOT.'includes/Smarty-' . SMARTY_VERSION . '/Smarty.class.php');
	$tpl = new Smarty;
	$template_subdir = 'templates/default/';
	$tpl->plugins_dir[] = WEB_ROOT . 'includes/smarty_plugins';
	$tpl->template_dir = WEB_ROOT . $template_subdir;
	$tpl->compile_dir = WEB_ROOT . 'templates_c/';
	$tpl->cache_dir = WEB_ROOT . 'cache/';
	$tpl->config_dir = WEB_ROOT . 'configs/';

	$tpl->assign("template_subdir",$template_subdir);
	$tpl->assign("PAGENAME",basename($_SERVER["SCRIPT_NAME"]));
	$tpl->assign("GALETTE_VERSION",GALETTE_VERSION);
?>
