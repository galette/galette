<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * Type:     function
 * Name:     _T
 * Purpose:  Translations for Galette
 * Params:   string            String to translate
 *
 * Author:   Johan Cwiklinski
 * Modified: 2008/07/17
 * Version:  0.1
 * -------------------------------------------------------------
 */
function smarty_function__T($params, &$smarty)
{
	extract($params);
	return _T($string);
}
?>
