<?
	if (!function_exists("_T"))
	{
		function _T($chaine)
		{
			if (isset($GLOBALS["lang"]))
			{
				if (!isset($GLOBALS["lang"][$chaine]))
					return $chaine." (not translated)";
				elseif ($GLOBALS["lang"][$chaine]=="")
					return $chaine." (not translated)";
				else
					return $GLOBALS["lang"][$chaine];
			}
			else
				return _($chaine);
		}
	}
?>
