<?php
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
	
	$tpl->assign('template_subdir', $template_subdir);
	$tpl->assign('jquery_dir', 'includes/jquery/');
	$tpl->assign('htmledi_dir', 'includes/tiny_mce/');
	$tpl->assign('scripts_dir', 'includes/');
	$tpl->assign('PAGENAME', basename($_SERVER['SCRIPT_NAME']));
	/** FIXME: on certains pages PHP notice that GALETTE_VERSION does not exists although it appears correctly*/
	$tpl->assign('GALETTE_VERSION', GALETTE_VERSION);
	$tpl->assign('galette_lang', $i18n->getAbbrev());
	$tpl->assign('pref_slogan', $preferences['pref_slogan']);
	$tpl->assign('pref_editor_enabled', $preferences['pref_editor_enabled']);
?>
