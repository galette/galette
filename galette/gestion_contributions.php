<? 
 
/* gestion_contributions.php
 * - Récapitulatif des contributions
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
	
	$filtre_id_adh = "";
	
	if ($_SESSION["logged_status"]==0) 
		header("location: index.php");
	if ($_SESSION["admin_status"]==0) 
		$_SESSION["filtre_cotis_adh"] = $_SESSION["logged_id_adh"];
	else
	{
		if (isset($_GET["id_adh"]))
		{
			if (is_numeric($_GET["id_adh"]))
				$_SESSION["filtre_cotis_adh"]=$_GET["id_adh"];
			else
				$_SESSION["filtre_cotis_adh"]="";
		}
	}		

        if (isset($_GET["contrib_filter_1"]))
	if (ereg("^([0-9]{2})/([0-9]{2})/([0-9]{4})$", $_GET["contrib_filter_1"], $array_jours))
	{
		if (checkdate($array_jours[2],$array_jours[1],$array_jours[3]))
			$_SESSION["filtre_date_cotis_1"]=$_GET["contrib_filter_1"];
		else
			$error_detected[] = _T("- Non valid date!");
	}
	elseif (ereg("^([0-9]{4})$", $_GET["contrib_filter_1"], $array_jours))
		$_SESSION["filtre_date_cotis_1"]="01/01/".$array_jours[1];
	elseif ($_GET["contrib_filter_1"]=="")
		$_SESSION["filtre_date_cotis_1"]="";
	else
		$error_detected[] = _T("- Wrong date format (dd/mm/yyyy)!");

	if (isset($_GET["contrib_filter_2"]))
	if (ereg("^([0-9]{2})/([0-9]{2})/([0-9]{4})$", $_GET["contrib_filter_2"], $array_jours))
	{
		if (checkdate($array_jours[2],$array_jours[1],$array_jours[3]))
			$_SESSION["filtre_date_cotis_2"]=$_GET["contrib_filter_2"];
		else
			$error_detected[] = _T("- Non valid date!");
	}
	elseif (ereg("^([0-9]{4})$", $_GET["contrib_filter_2"], $array_jours))
		$_SESSION["filtre_date_cotis_2"]="01/01/".$array_jours[1];
	elseif ($_GET["contrib_filter_2"]=="")
		$_SESSION["filtre_date_cotis_2"]="";
	else
		$error_detected[] = _T("- Wrong date format (dd/mm/yyyy)!");

	$page = 1;
	if (isset($_GET["page"]))
		$page = $_GET["page"];

	// Tri	
	if (isset($_GET["tri"]))
	{
		if ($_SESSION["tri_cotis"]==$_GET["tri"])
			$_SESSION["tri_cotis_sens"]=($_SESSION["tri_cotis_sens"]+1)%2;
		else
		{
			$_SESSION["tri_cotis"]=$_GET["tri"];
			$_SESSION["tri_cotis_sens"]=0;
		}
	}

	if ($_SESSION["admin_status"]==1) 
	if (isset($_GET["sup"]))
	{
		// recherche adherent
		$requetesel = "SELECT id_adh
				FROM ".PREFIX_DB."cotisations 
				WHERE id_cotis=".$DB->qstr($_GET["sup"]); 
		$result_adh = &$DB->Execute($requetesel);
		if (!$result_adh->EOF)
		{			
			$id_adh = $result_adh->fields["id_adh"];

			$requetesup = "SELECT nom_adh, prenom_adh FROM ".PREFIX_DB."adherents WHERE id_adh=".$DB->qstr($id_adh);
			$resultat = $DB->Execute($requetesup);
			if (!$resultat->EOF)
			{			
				// supression record cotisation
				$requetesup = "DELETE FROM ".PREFIX_DB."cotisations 
				    	    WHERE id_cotis=".$DB->qstr($_GET["sup"]); 
				$DB->Execute($requetesup);
			
				// mise a jour de l'échéance
				$date_fin = get_echeance($DB, $id_adh);
				if ($date_fin!=""){
				  //$date_fin_update = $DB->DBDate(mktime(0,0,0,$date_fin[1],$date_fin[0],$date_fin[2]));
				  $date_fin_update = "'".$date_fin[2]."-".$date_fin[1]."-".$date_fin[0]."'";
				} else {
				  $date_fin_update = "NULL";	
				}
				$requeteup = "UPDATE ".PREFIX_DB."adherents
					    SET date_echeance=".$date_fin_update."
					    WHERE id_adh=".$DB->qstr($id_adh);
				$DB->Execute($requeteup);
 				dblog("Contribution deleted:",strtoupper($resultat->fields[0])." ".$resultat->fields[1],$requetesup);							
 			}
 			$resultat->Close();
 		}
 		$result_adh->Close();
	}

	$date_cotis_format = &$DB->SQLDate('d/m/Y',PREFIX_DB.'cotisations.date_cotis');
	$requete[0] = "SELECT $date_cotis_format AS date_cotis,
			".PREFIX_DB."cotisations.id_cotis, 
			".PREFIX_DB."cotisations.id_adh, 
			".PREFIX_DB."cotisations.duree_mois_cotis, 
			".PREFIX_DB."cotisations.montant_cotis, 
			".PREFIX_DB."adherents.nom_adh, 
			".PREFIX_DB."adherents.prenom_adh,
			".PREFIX_DB."types_cotisation.libelle_type_cotis
			FROM ".PREFIX_DB."cotisations,".PREFIX_DB."adherents,".PREFIX_DB."types_cotisation
			WHERE ".PREFIX_DB."cotisations.id_adh=".PREFIX_DB."adherents.id_adh
			AND ".PREFIX_DB."types_cotisation.id_type_cotis=".PREFIX_DB."cotisations.id_type_cotis ";
	$requete[1] = "SELECT count(id_cotis)
			FROM ".PREFIX_DB."cotisations
			WHERE 1=1 ";

	// phase filtre
	
	if ($_SESSION["filtre_cotis_adh"]!="")
	{
		$requete[0] .= "AND ".PREFIX_DB."cotisations.id_adh='" . $_SESSION["filtre_cotis_adh"] . "' ";
		$requete[1] .= "AND ".PREFIX_DB."cotisations.id_adh='" . $_SESSION["filtre_cotis_adh"] . "' ";
	}
		
	// date filter
	if ($_SESSION["filtre_date_cotis_1"]!="")
	{
	   ereg("^([0-9]{2})/([0-9]{2})/([0-9]{4})$", $_SESSION["filtre_date_cotis_1"], $array_jours);
	   //$datemin = $DB->DBDate(mktime(0,0,0,$array_jours[2],$array_jours[1],$array_jours[3]));
	   $datemin = "'".$array_jours[3]."-".$array_jours[2]."-".$array_jours[1]."'";
	   $requete[0] .= "AND ".PREFIX_DB."cotisations.date_cotis >= " . $datemin . " ";
	   $requete[1] .= "AND ".PREFIX_DB."cotisations.date_cotis >= " . $datemin . " ";
	}
	if ($_SESSION["filtre_date_cotis_2"]!="")
	{
	   ereg("^([0-9]{2})/([0-9]{2})/([0-9]{4})$", $_SESSION["filtre_date_cotis_2"], $array_jours);
	   //$datemax = $DB->DBDate(mktime(0,0,0,$array_jours[2],$array_jours[1],$array_jours[3]));
	   $datemax = "'".$array_jours[3]."-".$array_jours[2]."-".$array_jours[1]."'";
	   $requete[0] .= "AND ".PREFIX_DB."cotisations.date_cotis <= " . $datemax . " ";
	   $requete[1] .= "AND ".PREFIX_DB."cotisations.date_cotis <= " . $datemax . " ";
	}

	// phase de tri
	
	if ($_SESSION["tri_cotis_sens"]=="0")
		$tri_cotis_sens_txt="ASC";
	else
		$tri_cotis_sens_txt="DESC";	
								
	$requete[0] .= "ORDER BY ";

	// tri par adherent
	if ($_SESSION["tri_cotis"]=="1")
		$requete[0] .= "nom_adh ".$tri_cotis_sens_txt.", prenom_adh ".$tri_cotis_sens_txt.",";
		
	// tri par type
	elseif ($_SESSION["tri_cotis"]=="2")
		$requete[0] .= "libelle_type_cotis ".$tri_cotis_sens_txt.",";
	
	// tri par montant
	elseif ($_SESSION["tri_cotis"]=="3")
		$requete[0] .= "montant_cotis ".$tri_cotis_sens_txt.",";

	// tri par duree
	elseif ($_SESSION["tri_cotis"]=="4")
		$requete[0] .= "duree_mois_cotis ".$tri_cotis_sens_txt.",";

	// defaut : tri par date
	$requete[0] .= " ".PREFIX_DB."cotisations.date_cotis ".$tri_cotis_sens_txt; 
	
	// $resultat = &$DB->Execute($requete[0]); 
	$resultat = &$DB->SelectLimit($requete[0],PREF_NUMROWS,($page-1)*PREF_NUMROWS);
	$nb_contributions = &$DB->Execute($requete[1]); 
	$contributions = array();

	if ($nb_contributions->fields[0]%PREF_NUMROWS==0) 
		$nbpages = intval($nb_contributions->fields[0]/PREF_NUMROWS);
	else 
		$nbpages = intval($nb_contributions->fields[0]/PREF_NUMROWS)+1;
		
	$compteur = 1+($page-1)*PREF_NUMROWS;
	while(!$resultat->EOF) 
	{ 
		if ($resultat->fields["duree_mois_cotis"]!="0")
			$row_class = "cotis-normal";
		else
			$row_class = "cotis-give";
			
		$contributions[$compteur]["class"]=$row_class;
		$contributions[$compteur]["id_cotis"]=$resultat->fields['id_cotis'];
		$contributions[$compteur]["date"]=$resultat->fields['date_cotis'];
		$contributions[$compteur]["id_adh"]=$resultat->fields['id_adh'];
		$contributions[$compteur]["nom"]=htmlentities(strtoupper($resultat->fields['nom_adh']),ENT_QUOTES);
		$contributions[$compteur]["prenom"]=htmlentities($resultat->fields['prenom_adh'], ENT_QUOTES);
		$contributions[$compteur]["libelle_type_cotis"]=$resultat->fields['libelle_type_cotis'];;
		$contributions[$compteur]["montant_cotis"]=$resultat->fields['montant_cotis'];;
		$contributions[$compteur]["duree_mois_cotis"]=$resultat->fields['duree_mois_cotis'];;
		$compteur++;
		$resultat->MoveNext();
	}
	$resultat->Close();
	
	// if viewing a member's contributions, show deadline
	if ($_SESSION["filtre_cotis_adh"]!="")
	{
		$requete = "SELECT date_echeance, bool_exempt_adh
			    FROM ".PREFIX_DB."adherents
			    WHERE id_adh='".$_SESSION["filtre_cotis_adh"]."'";
		$resultat = $DB->Execute($requete);
		if($resultat->fields[1])
		{
			$statut_cotis = _T("Freed of dues");
			$statut_class = 'cotis-exempt';
		}
		else
		{
			if ($resultat->fields[0]=="")
			{
				$statut_cotis = _T("Never contributed");
				$statut_class = 'cotis-never';			
			}
			else
			{
				$date_fin = split("-",$resultat->fields[0]);
				$ts_date_fin = mktime(0,0,0,$date_fin[1],$date_fin[2],$date_fin[0]);
				$aujourdhui = time();
				$difference = intval(($ts_date_fin - $aujourdhui)/(3600*24));
				if ($difference==0)
				{
					$statut_cotis = _T("Last day!");
					$statut_class = 'cotis-lastday';
				}
				elseif ($difference<0)
				{
					$statut_cotis = _T("Late of")." ".-$difference." "._T("days")." ("._T("since")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
					$statut_class = 'cotis-late';
				}
				else
				{
					if ($difference!=1)
						$statut_cotis = $difference." "._T("days remaining")." ("._T("ending on")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
					else
						$statut_cotis = $difference." "._T("day remaining")." ("._T("ending on")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
					if ($difference < 30)
						$statut_class = 'cotis-soon';
					else
						$statut_class = 'cotis-ok';	
				}		
			}
		}
		$tpl->assign("statut_cotis",$statut_cotis);
		$tpl->assign("statut_class",$statut_class);		
	}
	

	$tpl->assign("contributions",$contributions);
	$tpl->assign("nb_contributions",count($contributions));
	$tpl->assign("nb_pages",$nbpages);
	$tpl->assign("page",$page);
	$tpl->assign('filtre_options', array(
			0 => _T("All members"),
			3 => _T("Members up to date"),
			1 => _T("Close expiries"),
			2 => _T("Latecomers")));
	$tpl->assign('filtre_2_options', array(
			0 => _T("All the accounts"),
			1 => _T("Active accounts"),
			2 => _T("Inactive accounts")));
										
	$content = $tpl->fetch("gestion_contributions.tpl");
	$tpl->assign("content",$content);
	$tpl->display("page.tpl");
?>
