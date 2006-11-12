<?php
	// smarty-light declaration
	include(WEB_ROOT.'includes/smarty/Smarty.class.php');
	$tpl = new Smarty;
	$template_subdir = 'templates/default/';
	$tpl->template_dir = WEB_ROOT.$template_subdir;
	$tpl->compile_dir = WEB_ROOT.'templates_c/';
	$tpl->cache_dir = WEB_ROOT.'cache/';
	$tpl->config_dir = WEB_ROOT.'configs/';
	
	$tpl->assign("template_subdir",$template_subdir);
	$tpl->assign("PAGENAME",basename($_SERVER["SCRIPT_NAME"]));
	$tpl->assign("GALETTE_VERSION",GALETTE_VERSION);
?>
