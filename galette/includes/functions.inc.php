<? 
 
/* functions.inc.php
 * - Fonctions utilitaires
 * Copyright (c) 2003 Frédéric Jaqcuot
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
 */
 
	function makeRandomPassword()
	{
  		$salt = "abcdefghjkmnpqrstuvwxyz0123456789";
    		srand((double)microtime()*1000000);
          	$i = 0;
	        while ($i <= 6) 
		{
	        	$num = rand() % 33;
	        	$tmp = substr($salt, $num, 1);
	        	$pass = $pass . $tmp;
	        	$i++;
	      	}
	     	return $pass;
	}

	function isSelected($champ1, $champ2) { 
	  if ($champ1 == $champ2) { 
	    echo " selected"; 
	  } 
	} 
 
	function isChecked($champ1, $champ2) { 
	  if ($champ1 == $champ2) { 
	    echo " checked"; 
	  } 
	} 

	function txt_sqls($champ) { 
		return "'".str_replace("'", "\'", str_replace('\\', '', $champ))."'"; 
	}
	
	function is_valid_web_url($url) {
	  return (preg_match(
	  		'/^(http|https):\/\/'.
	  		'.*/i', $url, $m
	  		));
	}
	
/*
 *
 * is_valid_email(): an e-mail validation utility routine
 * Version 1.1.1 -- September 10, 2000
 *
 * Written by Michael A. Alderete
 * Please send bug reports and improvements to: <michael@aldosoft.com>
 *
 * This function matches a proposed e-mail address against a validating
 * regular expression. It's intended for use in web registration systems
 * and other places where the user is inputting their e-mail address and
 * you want to check that it's OK.
 *
 */

	function is_valid_email ($address) {
    return (preg_match(
        '/^[-!#$%&\'*+\\.\/0-9=?A-Z^_`{|}~]+'.   // the user name
        '@'.                                     // the ubiquitous at-sign
        '([-0-9A-Z]+\.)+' .                      // host, sub-, and domain names
        '([0-9A-Z]){2,4}$/i',                    // top-level domain (TLD)
        trim($address)));
	}
	
	function dblog($text, $query="")
	{
		if (PREF_LOG=="2")
		{
			$requete = "INSERT INTO logs (date_log, ip_log, adh_log, text_log) VALUES (" . $GLOBALS["DB"]->DBTimeStamp(time()) . ", " . $GLOBALS["DB"]->qstr($_SERVER["REMOTE_ADDR"]) . ", " . $GLOBALS["DB"]->qstr($_SESSION["logged_nom_adh"]) . ", " . $GLOBALS["DB"]->qstr($text."\n".$query) . ");";
			$GLOBALS["DB"]->Execute($requete);
		}
		elseif (PREF_LOG=="1")
		{
			$requete = "INSERT INTO logs (date_log, ip_log, adh_log, text_log) VALUES (" . $GLOBALS["DB"]->DBTimeStamp(time()) . ", " . $GLOBALS["DB"]->qstr($_SERVER["REMOTE_ADDR"]) . ", " . $GLOBALS["DB"]->qstr($_SESSION["logged_nom_adh"]) . ", " . $GLOBALS["DB"]->qstr($text) . ");";
			$GLOBALS["DB"]->Execute($requete);
		}
	}
	
?>
