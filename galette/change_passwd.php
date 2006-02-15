<?php
/* change_passwd.php
 * - Change passwd
 * Copyright (c) 2005 Stéphane Salès
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
	include(WEB_ROOT."includes/functions.inc.php");
	include(WEB_ROOT."includes/session.inc.php");
  include_once(WEB_ROOT."includes/i18n.inc.php");
  include_once(WEB_ROOT."includes/smarty.inc.php");

	// initialize warnings
	$error_detected = array();
	$warning_detected = array();
	$hash = "";

	//TODO need to sanityze superglobals, see sanityze_superglobals_arrays
	// get hash id $_GET if passed by url, $_POST if passed by this form
	if (isset($_GET['hash']) && !empty($_GET['hash']) ) {
		$hash=$_GET['hash'];
	} else {
		if (isset($_POST['hash']) && !empty($_POST['hash']) ) {
			$hash=$_POST['hash'];
		}
	}
	if ( isset($hash) && !empty($hash) ) {
		$query = "SELECT id_adh from ".PREFIX_DB."tmppasswds where tmp_passwd=".txt_sqls($hash);
		$result = &$DB->Execute($query);
		if ($result->EOF) {
			$warning_detected = _T("We could'nt find this password in the database");
			//TODO need to clean die here
		} else {
			$id_adh = $result->fields[0];
		}
		// Validation
		if ( isset($_POST['valid']) && $_POST['valid'] == "1") {
 			if ($_POST["mdp_adh"]=="") {
				$error_detected[] = _T("No password");
			}
			//if ($_POST['mdp_adh2']==$_POST['mdp_adh'])
			if ( isset($_POST['mdp_adh2']) ) { 
				if ( strcmp($_POST["mdp_adh"],$_POST["mdp_adh2"]) ) {
					$error_detected[] = _T("- The passwords don't match!");
				} else {
					$passwd = $_POST['mdp_adh'];
					if (strlen($passwd)<4) {
						$error_detected[] = _T("- The password must be of at least 4 characters!");
					} else {
						$passwd = md5($passwd);
						$query = "UPDATE ".PREFIX_DB."adherents";
						$query .= " SET mdp_adh = '$passwd'";
						if (!$DB->Execute($query)) {
							$warning_detected = _T("There was a database error");
							//$warning_detected = $DB->ErrorMsg();
						} else {
							dblog(_T("**Password changed**. id:")." \"" . $id_adh . "\"");
						}
					}
				}
			}
		}
	}
	else
	{
		header('location: index.php');
		die();
	}

	$tpl->assign("error_detected",$error_detected);
	$tpl->assign("warning_detected",$warning_detected);
	$tpl->assign("hash",$hash);

  // display page
	$tpl->display("change_passwd.tpl");
?>
