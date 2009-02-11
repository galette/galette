<?php

// Copyright © 2005 Stéphane Salès
// Copyright © 2007-2008 Johan Cwiklinski
//
// This file is part of Galette (http://galette.tuxfamily.org).
//
// Galette is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Galette is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Galette. If not, see <http://www.gnu.org/licenses/>.

/**
 * Changement du mot de passe
 *
 * @package Galette
 * 
 * @author     Stéphane Salès
 * @copyright  2005 Stéphane Salès
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.62
 */

require_once('includes/galette.inc.php');

	// initialize warnings
	$error_detected = array();
	$warning_detected = array();
	$hash = "";

	//TODO need to sanityze superglobals, see sanityze_superglobals_arrays
	// get hash id, $_GET if passed by url, $_POST if passed by this form
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
			$warning_detected = _T("This link is no longer valid. You should <a href='lostpasswd.php'>ask to retrieve your password</a> again.");
			$head_redirect = "<meta http-equiv=\"refresh\" content=\"30;url=index.php\" />";
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
						$query .= " WHERE id_adh = '$id_adh'";
						if (!$DB->Execute($query)) {
							$warning_detected = _T("There was a database error");
						} else {
							//delete temporary password from table
							$query = "DELETE from ".PREFIX_DB."tmppasswds where tmp_passwd=".txt_sqls($hash);
							if (!$DB->Execute($query)) {
								$warning_detected = _T("There was a database error");
							}else{
								$hist->add("**Password changed**. id:"." \"" . $id_adh . "\"");
								$warning_detected = _T("Password changed, you will be redirected to login page");
								$head_redirect = "<meta http-equiv=\"refresh\" content=\"10;url=index.php\" />";
							}
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
	$tpl->assign("head_redirect", $head_redirect);
	$tpl->assign("hash",$hash);

  // display page
	$tpl->display("change_passwd.tpl");
?>
