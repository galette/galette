<? 
 
/* session.inc.php
 * - Gestion de la session
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
 
	session_start(); 
	if (!isset($_SESSION["logged_status"]) ||
			isset($HTTP_POST_VARS["logout"]) ||
			isset($HTTP_GET_VARS["logout"]))
	{
		if (isset($HTTP_POST_VARS["logout"]) ||
		    isset($HTTP_GET_VARS["logout"])){
			dblog(_("Log off"));
		}
		$_SESSION["admin_status"]=0;
		$_SESSION["logged_status"]=0;
		$_SESSION["logged_id_adh"]=0;
		$_SESSION["logged_nom_adh"]="";
		$_SESSION["filtre_adh"]=0;
		$_SESSION["filtre_adh_2"]=1;
		$_SESSION["filtre_date_cotis_1"]="";
		$_SESSION["filtre_date_cotis_2"]="";
		$_SESSION["tri_adh"]=0;
		$_SESSION["tri_adh_sens"]=0;
		$_SESSION["tri_log"]=0;
		$_SESSION["tri_log_sens"]=0;
		$_SESSION["filtre_cotis"]=0;
		$_SESSION["tri_cotis"]=0;
		$_SESSION["tri_cotis_sens"]=1;
		$_SESSION["filtre_cotis_adh"]="";
		$_SESSION["pref_lang"]=PREF_LANG;
	}

?>
