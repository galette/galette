<?
	include("includes/config.inc.php");
	if (!isset($_GET["id_adh"]))
	{
		if (isset($_GET["pw"]))
		{
			if (preg_match("/^pw_[a-f0-9]*\.png$/",$_GET["pw"]))
			{
				$header = "Content-type: image/png";
				$readfile = WEB_ROOT . "photos/".$_GET["pw"];
			}
		}
		else
		{
			$header = "Content-type: image/png";
			$readfile = WEB_ROOT . "photos/default.png";
		}
	}
	elseif (!is_numeric($_GET["id_adh"]))
	{
	        if ($_GET["id_adh"]=="logo")
       		{
        		if (isset($_GET["tn"]))
        	    	{
				$header = "Content-type: image/jpeg";
				$readfile = "photos/tn_logo.jpg";
			}
			else
        		{
				$header = "Content-type: image/jpeg";
				$readfile = "photos/logo.jpg";
		        }
	        }
	        else
	        {
            		$header = "Content-type: image/png";
            		$readfile = WEB_ROOT . "photos/default.png";
       		}
	}
	else
	{
		$id_adh = $_GET["id_adh"];
		if (isset($_GET["tn"]))
		{
			if (file_exists(WEB_ROOT . "photos/tn_" . $id_adh . ".jpg"))
			{
				$header = "Content-type: image/jpeg";
				$readfile = "photos/tn_" . $id_adh . ".jpg";
			}
			elseif (file_exists(WEB_ROOT . "photos/tn_" . $id_adh . ".gif"))
			{
				$header = "Content-type: image/gif";
				$readfile = "photos/tn_" . $id_adh . ".gif";
			}
			elseif (file_exists(WEB_ROOT . "photos/tn_" . $id_adh . ".png"))
			{
				$header = "Content-type: image/png";
				$readfile = "photos/tn_" . $id_adh . ".png";
			}
			else
			{
				$header = "Content-type: image/png";
				$readfile = WEB_ROOT . "photos/default.png";
			}
		}
		else
		{
			if (file_exists(WEB_ROOT . "photos/" . $id_adh . ".jpg"))
			{
				$header = "Content-type: image/jpeg";
				$readfile = "photos/" . $id_adh . ".jpg";
			}
			elseif (file_exists(WEB_ROOT . "photos/" . $id_adh . ".gif"))
			{
				$header = "Content-type: image/gif";
				$readfile = "photos/" . $id_adh . ".gif";
			}
			elseif (file_exists(WEB_ROOT . "photos/" . $id_adh . ".png"))
			{
				$header = "Content-type: image/png";
				$readfile = "photos/" . $id_adh . ".png";
			}
			else
			{
				$header = "Content-type: image/png";
				$readfile = WEB_ROOT . "photos/default.png";
			}
		}
	}

	header($header);
	readfile($readfile);
?> 
