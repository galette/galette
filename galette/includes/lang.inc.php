<?
if (LANG_INC!="lang_inc"){
        define(LANG_INC,"lang_inc");
        if (isset($GLOBALS["pref_lang"])){
	  include(WEB_ROOT."lang/lang_".$GLOBALS["pref_lang"].".php");
	} else {
	  include(WEB_ROOT."lang/lang_".PREF_LANG.".php");
	}
	function _T($chaine)
	{
		// echo "$chaine";die();
		if (!isset($GLOBALS["lang"][$chaine]))
			return $chaine." (not translated)";
		elseif ($GLOBALS["lang"][$chaine]=="")
			return $chaine." (not translated)";
		else
			return $GLOBALS["lang"][$chaine];
	}
}
?>
