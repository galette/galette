<? 
/* preferences.php
 * - Preferences Galette
 * Copyright (c) 2004 Frédéric Jaqcuot
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

	include("includes/config.inc.php");
	include(WEB_ROOT."includes/database.inc.php"); 
	include(WEB_ROOT."includes/session.inc.php");
	include(WEB_ROOT."includes/functions.inc.php"); 
        include(WEB_ROOT."includes/i18n.inc.php");
	include(WEB_ROOT."includes/smarty.inc.php");

	if ($_SESSION["logged_status"]==0) 
		header("location: index.php");
	if ($_SESSION["admin_status"]==0) 
		header("location: voir_adherent.php");
	
	// initialize warnings
	$error_detected = array();
	$warning_detected = array();
	$confirm_detected = array();

	// flagging required fields
	$required = array(
			'pref_nom' => 1,
			'pref_lang' => 1,
			'pref_numrows' => 1,
			'pref_log' => 1,
			'pref_email_nom' => 1,
			'pref_etiq_marges' => 1,
			'pref_etiq_hspace' => 1,
			'pref_etiq_vspace' => 1,
			'pref_etiq_hsize' => 1,
			'pref_etiq_vsize' => 1,
			'pref_etiq_cols' => 1,
			'pref_etiq_rows' => 1,
			'pref_etiq_corps' => 1,
			'pref_admin_login' => 1,
			'pref_admin_pass' => 1);

	// Validation
	if (isset($_POST['valid']))
	{
  		// verification de champs
	  	$insert_values = array();

		// obtain fields
		$requete = "SELECT nom_pref
			    FROM ".PREFIX_DB."preferences";
		$result=$DB->Execute($requete);
		while (!$result->EOF)
		{
			$fieldname = $result->fields['nom_pref'];

			if (isset($_POST[$fieldname]))
				$value=trim($_POST[$fieldname]);
			else			
				$value="";
			
			// fill up pref structure
			$pref[$fieldname] = htmlentities(stripslashes($value),ENT_QUOTES);
			
			// now, check validity
			if ($value != '')
			switch ($fieldname)
			{
				case 'pref_email':
					if (!is_valid_email($value))
					        $error_detected[] = _T("- Non-valid E-Mail address!");
					break;
				case 'pref_admin_login':
					if (strlen($value)<4)
						$error_detected[] = _T("- The username must be composed of at least 4 characters!");
					else
					{
						//check if login is already taken
						$requete2 = "SELECT id_adh
								FROM ".PREFIX_DB."adherents
								WHERE login_adh=". $DB->qstr($value, true);
						$result2 = &$DB->Execute($requete2);
						if (!$result2->EOF)
							$error_detected[] = _T("- This username is already used by another member !");
					}
					break;
				case 'pref_numrows':
				case 'pref_etiq_marges':
				case 'pref_etiq_hspace':
				case 'pref_etiq_vspace':
				case 'pref_etiq_hsize':
				case 'pref_etiq_vsize':
				case 'pref_etiq_cols':
				case 'pref_etiq_rows':
				case 'pref_etiq_corps':
					// prevent division by zero
					if ($fieldname=='pref_numrows' && $value=='0')
						$value = '1';
					if (!is_numeric($value) || $value <0)
						$error_detected[] = "<LI>"._T("- The numbers and measures have to be integers!")."</LI>";
					break;
				case 'pref_admin_pass':
					if (strlen($value)<4)
						$error_detected[] = _T("- The password must be of at least 4 characters!");
					break;
			}
			$insert_values[$fieldname] = $value;
			$result->MoveNext();
		}
		$result->Close();
 	 		
		// missing relations
		if (isset($pref['pref_mail_method']))
		{
			if ($pref['pref_mail_method']==2)
			{
				if (!isset($pref['pref_mail_smtp']))
					$error_detected[] = _T("- You must indicate the SMTP server you wan't to use!");
				elseif ($pref['pref_mail_smtp']=='')
					$error_detected[] = _T("- You must indicate the SMTP server you wan't to use!");
			}
		}
		
		// missing required fields?
		while (list($key,$val) = each($required))
		{
			if (!isset($pref[$key]))
				$error_detected[] = _T("- Mandatory field empty.")." ".$key;
			elseif (isset($pref[$key]))
				if (trim($pref[$key])=='')
					$error_detected[] = _T("- Mandatory field empty.")." ".$key;
		}

		if (count($error_detected)==0)
		{
			// empty preferences
			$requete = "DELETE FROM ".PREFIX_DB."preferences";
			$DB->Execute($requete);
		
			// insert new preferences
			while (list($champ,$valeur)=each($insert_values))
			{
				$valeur = stripslashes($valeur);
				$requete = "INSERT INTO ".PREFIX_DB."preferences 
					    (nom_pref, val_pref)
					    VALUES (".$DB->qstr($champ).",".$DB->qstr($valeur).");";
				$DB->Execute($requete);
			}
		
			// TODO: Insert logo in database
		}
	}
	else
	{
		// collect data
		$requete = "SELECT * 
		  	    FROM ".PREFIX_DB."preferences";
		$result = &$DB->Execute($requete);
        	if ($result->EOF)
	                header("location: index.php");
		else
		{
			while (!$result->EOF)
			{
				$pref[$result->fields['nom_pref']] = htmlentities(stripslashes(addslashes($result->fields['val_pref'])), ENT_QUOTES);
				$result->MoveNext();
			}
		}
		$result->Close();
	}
		
	$tpl->assign("pref",$pref);
	$tpl->assign("required",$required);
	$tpl->assign("languages",drapeaux());
	$tpl->assign("error_detected",$error_detected);
	
	// page genaration
	$content = $tpl->fetch("preferences.tpl");
	$tpl->assign("content",$content);
	$tpl->display("page.tpl");
?>
