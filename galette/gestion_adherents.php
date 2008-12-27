<?php

// Copyright © 2003 Frédéric Jaqcuot
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
 * Récapitulatif des adhérents
 *
 * Affichage de la liste des adhérents et possibilités
 * de tri en fonction:
 * - du statut de membre
 * - de l'état de la cotisation
 * - de l'état du compte
 * - du contenu de champs texte 
 *
 * @package    Galette
 *
 * @author     Frédéric Jaqcuot
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2003 Frédéric Jaqcuot
 * @copyright  2007-2008 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.62
 */

require_once('includes/galette.inc.php');

if( !$login->isLogged() ){
	header("location: index.php");
	die();
}elseif( !$login->isAdmin() ){
	header("location: voir_adherent.php");
	die();
}

$error_detected = array();
// Set caller page ref for cards error reporting	
$_SESSION['galette']['caller']='gestion_adherents.php';	

if (isset($_POST['cards'])) {
	$qstring = 'carte_adherent.php';
	if (isset($_POST["member_sel"])) {
		$_SESSION['galette']['cards'] = $_POST["member_sel"];
		header('location: '.$qstring);
	} else {
		$error_detected[] = _T("No member was selected, please check at least one name.");
	}
}
	
if (isset($_POST['labels'])) {
	$qstring = 'etiquettes_adherents.php';
	if (isset($_POST["member_sel"])) {
		$_SESSION['galette']['labels'] = $_POST["member_sel"];
		header('location: '.$qstring);
	} else
		$error_detected[] = _T("No member was selected, please check at least one name.");
}

if (isset($_POST['mailing'])) {
	$qstring = 'mailing_adherents.php';
	if (isset($_POST["member_sel"])) {
		$_SESSION['galette']['mailing'] = $_POST["member_sel"];
		header('location: '.$qstring);
	} else
		$error_detected[] = _T("No member was selected, please check at least one name.");
}

if (isset($_SESSION['galette']['pdf_error']) && $_SESSION['galette']['pdf_error']) {
	$error_detected[] = $_SESSION['galette']['pdf_error_msg'];
	unset($_SESSION['galette']['pdf_error_msg']);
	unset($_SESSION['galette']['pdf_error']);
}

$members = array();

// Filters
$page = 1;
if (isset($_GET["page"]))
	$page = $_GET["page"];

if (isset($_GET["filtre_nom"]))
	$_SESSION["filtre_adh_nom"]=trim(stripslashes(htmlspecialchars($_GET["filtre_nom"],ENT_QUOTES)));

if (isset($_GET["filtre"]))
	if (is_numeric($_GET["filtre"]))
		$_SESSION["filtre_adh"]=$_GET["filtre"];

if (isset($_GET["filtre_2"]))
	if (is_numeric($_GET["filtre_2"]))
		$_SESSION["filtre_adh_2"]=$_GET["filtre_2"];

if (isset($_GET["filtre_fld"]))
	if (is_numeric($_GET["filtre_fld"]))
		$_SESSION["filtre_adh_fld"]=$_GET["filtre_fld"];

$numrows = PREF_NUMROWS;
if (isset($_GET["nbshow"]))
	if (is_numeric($_GET["nbshow"]))
		$numrows = $_GET["nbshow"];

// Sorting
if (isset($_GET["tri"]))
	if (is_numeric($_GET["tri"]))
	{
		if ($_SESSION["tri_adh"]==$_GET["tri"])
			$_SESSION["tri_adh_sens"]=($_SESSION["tri_adh_sens"]+1)%2;
		else
		{
			$_SESSION["tri_adh"]=$_GET["tri"];
			$_SESSION["tri_adh_sens"]=0;
		}
	}
	
	if (isset($_GET["sup"]) || isset($_POST["delete"]))
	{
		$array_sup = array();
		if (isset($_GET["sup"]))
		{
			if (is_numeric($_GET["sup"]))
				$array_sup[] = $_GET["sup"];
		}
		else
		{
			if (isset($_POST["member_sel"]))
			foreach ($_POST["member_sel"] as $supval)
				if (is_numeric($supval))
					$array_sup[] = $supval;
		}
		
		foreach ($array_sup as $supval)
		{
			$requetesup = "SELECT nom_adh, prenom_adh FROM ".PREFIX_DB."adherents WHERE id_adh=".$DB->qstr($supval, get_magic_quotes_gpc());
			$resultat = $DB->Execute($requetesup);
			if (!$resultat->EOF)
			{
				// supression record adh�rent
				$requetesup = "DELETE FROM ".PREFIX_DB."adherents 
						WHERE id_adh=".$DB->qstr($supval, get_magic_quotes_gpc()); 
				$DB->Execute($requetesup); 		
				dblog("Delete the member card (and dues)",strtoupper($resultat->fields[0])." ".$resultat->fields[1],$requetesup);

				// suppression records cotisations
				$requetesup = "DELETE FROM ".PREFIX_DB."cotisations 
						WHERE id_adh=" . $DB->qstr($supval, get_magic_quotes_gpc()); 
				$DB->Execute($requetesup);

				// erase custom fields
				$requetesup = "DELETE FROM ".PREFIX_DB."adh_info
						WHERE id_adh=".$DB->qstr($supval, get_magic_quotes_gpc());
				$DB->Execute($requetesup);

				// erase picture
				$requetesup = "DELETE FROM ".PREFIX_DB."pictures
						WHERE id_adh=".$DB->qstr($supval, get_magic_quotes_gpc());
				$DB->Execute($requetesup);
			}
			$resultat->Close();
			header ('location: gestion_adherents.php');
 		}
	}

	// selection des adherents et application filtre / tri
	$requete[0] = "SELECT id_adh, nom_adh, prenom_adh, pseudo_adh, activite_adh,
		       libelle_statut, bool_exempt_adh, titre_adh, email_adh, bool_admin_adh, date_echeance, date_crea_adh
		       FROM ".PREFIX_DB."adherents, ".PREFIX_DB."statuts
		       WHERE ".PREFIX_DB."adherents.id_statut=".PREFIX_DB."statuts.id_statut ";
	$requete[1] = "SELECT count(id_adh)
		       FROM ".PREFIX_DB."adherents 
		       WHERE 1=1 ";
	
	// Advanced filter
	if ($_SESSION["filtre_adh_nom"]!="")
	{
		$token = " like '%".$_SESSION["filtre_adh_nom"]."%' ";
		switch ($_SESSION["filtre_adh_fld"])
		{
		//	0 => Name
		case 0:
			$concat1 = $DB->Concat(PREFIX_DB."adherents.nom_adh",$DB->Qstr(" "),PREFIX_DB."adherents.prenom_adh",$DB->Qstr(" "),PREFIX_DB."adherents.pseudo_adh");
			$concat2 = $DB->Concat(PREFIX_DB."adherents.prenom_adh",$DB->Qstr(" "),PREFIX_DB."adherents.nom_adh",$DB->Qstr(" "),PREFIX_DB."adherents.pseudo_adh");
			$requete[0] .= "AND (".$concat1.$token;
			$requete[0] .= "OR ".$concat2.$token.") ";
			$requete[1] .= "AND (".$concat1.$token;
			$requete[1] .= "OR ".$concat2.$token.") ";
			break;
		// 1 => Address
		case 1:
			$requete[0] .= "AND (".PREFIX_DB."adherents.adresse_adh ".$token;
			$requete[0] .= "OR ".PREFIX_DB."adherents.adresse2_adh ".$token;
			$requete[0] .= "OR ".PREFIX_DB."adherents.cp_adh ".$token;
			$requete[0] .= "OR ".PREFIX_DB."adherents.ville_adh ".$token;
			$requete[0] .= "OR ".PREFIX_DB."adherents.pays_adh ".$token.") ";
			$requete[1] .= "AND (".PREFIX_DB."adherents.adresse_adh ".$token;
			$requete[1] .= "OR ".PREFIX_DB."adherents.adresse2_adh ".$token;
			$requete[1] .= "OR ".PREFIX_DB."adherents.cp_adh ".$token;
			$requete[1] .= "OR ".PREFIX_DB."adherents.ville_adh ".$token;
			$requete[1] .= "OR ".PREFIX_DB."adherents.pays_adh ".$token.") ";
			break;
		//	2 => Email,URL,IM
		case 2:
			$requete[0] .= "AND (".PREFIX_DB."adherents.email_adh ".$token;
			$requete[0] .= "OR ".PREFIX_DB."adherents.url_adh ".$token;
			$requete[0] .= "OR ".PREFIX_DB."adherents.msn_adh ".$token;
			$requete[0] .= "OR ".PREFIX_DB."adherents.icq_adh ".$token;
			$requete[0] .= "OR ".PREFIX_DB."adherents.jabber_adh ".$token.") ";
			$requete[1] .= "AND (".PREFIX_DB."adherents.email_adh ".$token;
			$requete[1] .= "OR ".PREFIX_DB."adherents.url_adh ".$token;
			$requete[1] .= "OR ".PREFIX_DB."adherents.msn_adh ".$token;
			$requete[1] .= "OR ".PREFIX_DB."adherents.icq_adh ".$token;
			$requete[1] .= "OR ".PREFIX_DB."adherents.jabber_adh ".$token.") ";
			break;
		//	3 => Job
		case 3:
			$requete[0] .= "AND ".PREFIX_DB."adherents.prof_adh ".$token;
			$requete[1] .= "AND ".PREFIX_DB."adherents.prof_adh ".$token;
			break;
		//	4 => Infos
		case 4:
			$requete[0] .= "AND (".PREFIX_DB."adherents.info_adh ".$token;
			$requete[0] .= "OR ".PREFIX_DB."adherents.info_public_adh ".$token.") ";
			$requete[1] .= "AND (".PREFIX_DB."adherents.info_adh ".$token;
			$requete[1] .= "OR ".PREFIX_DB."adherents.info_public_adh ".$token.") ";
			break;
		}
	}
	// filtre d'affichage des adherents activés/desactivés
	if ($_SESSION["filtre_adh_2"]=="1")
	{
		$requete[0] .= "AND ".PREFIX_DB."adherents.activite_adh='1' ";
		$requete[1] .= "AND ".PREFIX_DB."adherents.activite_adh='1' ";
	}
	elseif ($_SESSION["filtre_adh_2"]=="2")
	{
		$requete[0] .= "AND ".PREFIX_DB."adherents.activite_adh='0' ";
		$requete[1] .= "AND ".PREFIX_DB."adherents.activite_adh='0' ";
	}

	// filtre d'affichage des adherents retardataires
	if ($_SESSION["filtre_adh"]=="2")
	{
		$requete[0] .= "AND date_echeance < ".$DB->DBDate(time())." ";
		$requete[1] .= "AND date_echeance < ".$DB->DBDate(time())." ";
	}

	// filtre d'affichage des adherents à jour
	if ($_SESSION["filtre_adh"]=="3")
	{
		$requete[0] .= "AND (date_echeance > ".$DB->DBDate(time())." OR bool_exempt_adh='1') ";
		$requete[1] .= "AND (date_echeance > ".$DB->DBDate(time())." OR bool_exempt_adh='1') ";
	}

	// filtre d'affichage des adherents bientot à echeance
	if ($_SESSION["filtre_adh"]=="1")
	{
		$requete[0] .= "AND date_echeance >= ".$DB->DBDate(time())."
			        AND date_echeance < ".$DB->OffsetDate(30)." ";
		$requete[1] .= "AND date_echeance >= ".$DB->DBDate(time())."
			        AND date_echeance < ".$DB->OffsetDate(30)." ";
	}
	// filtre d'affichage des adherents n'ayant jamais cotisé
	if ($_SESSION["filtre_adh"]=="4")
	{
		$requete[0] .= "AND isnull(date_echeance)";
		$requete[1] .= "AND isnull(date_echeance)";
	}

	
	// phase de tri	
	if ($_SESSION["tri_adh_sens"]=="0")
		$tri_adh_sens_txt="ASC";
	else
		$tri_adh_sens_txt="DESC";

	$requete[0] .= "ORDER BY ";
	
	// tri par pseudo
	if ($_SESSION["tri_adh"]=="1")
		$requete[0] .= "pseudo_adh ".$tri_adh_sens_txt.",";
		
	// tri par statut
	elseif ($_SESSION["tri_adh"]=="2")
		$requete[0] .= "priorite_statut ".$tri_adh_sens_txt.",";

	// tri par echeance
	elseif ($_SESSION["tri_adh"]=="3") {
    	if ($_SESSION["filtre_adh"]=="4") {
    		$requete[0] .= " date_crea_adh ".$tri_adh_sens_txt.",";
    	} else {
    		$requete[0] .= "bool_exempt_adh ".$tri_adh_sens_txt.", date_echeance ".$tri_adh_sens_txt.",";
    	}
    }	
	// defaut : tri par nom, prenom
	$requete[0] .= "nom_adh ".$tri_adh_sens_txt.", prenom_adh ".$tri_adh_sens_txt; 
	
	$nbadh = &$DB->Execute($requete[1]);
	if ($numrows==0)
		$resultat = &$DB->Execute($requete[0]);
	else
		$resultat = &$DB->SelectLimit($requete[0],$numrows,($page-1)*$numrows);

	if ($numrows==0)
		$nbpages = 1;
	else if ($nbadh->fields[0]%$numrows==0) 
		$nbpages = intval($nbadh->fields[0]/$numrows);
	else 
		$nbpages = intval($nbadh->fields[0]/$numrows)+1;
	if ($nbpages==0)
		$nbpages = 1;

	$compteur = 1+($page-1)*$numrows;
	while (!$resultat->EOF) 
	{ 
		// définition CSS pour adherent désactivé
		if ($resultat->fields[4]=="1")
			$row_class = "actif";
		else
			$row_class = "inactif";
			
		// temps d'adhésion
		if($resultat->fields[6] == "1")
		{
			$statut_cotis = _T("Freed of dues");
			$row_class .= " cotis-exempt";
		}
		else
		{
			if ($resultat->fields[10]=="")
			{
				$date_crea = explode("-",$resultat->fields[11]);
				$ts_date_crea = mktime(0,0,0,$date_crea[1],$date_crea[2],$date_crea[0]);
			    $difference = -intval(($ts_date_crea - time())/(3600*24));
				$statut_cotis = _T("Never contributed: Registered ").$difference._T(" days ago (since ").$date_crea[2]."/".$date_crea[1]."/".$date_crea[0].")";
				$row_class .= " cotis-never";
			}
			else
			{
				$date_fin = explode("-",$resultat->fields[10]);
				$ts_date_fin = mktime(0,0,0,$date_fin[1],$date_fin[2],$date_fin[0]);
				$aujourdhui = time();
				
				$difference = intval(($ts_date_fin - $aujourdhui)/(3600*24));
				if ($difference==0)
				{
					$statut_cotis = _T("Last day!");
					$row_class .= " cotis-lastday";
				}
				elseif ($difference<0)
				{
					$statut_cotis = _T("Late of ").-$difference." "._T("days")." ("._T("since")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
					$row_class .= " cotis-late";
				}
				else
				{
					if ($difference!=1)
						$statut_cotis = $difference." "._T("remaining days")." ("._T("ending on")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
					else
						$statut_cotis = $difference." "._T("remaining day")." ("._T("ending on")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
					if ($difference < 30)
						$row_class .= " cotis-soon";
					else
						$row_class .= " cotis-ok";	
				}				
			}
		}
		$members[$compteur]["class"]=$row_class;
		$members[$compteur]["genre"]=$resultat->fields[7];
		$members[$compteur]["email"]=$resultat->fields[8];
		$members[$compteur]["admin"]=$resultat->fields[9];
		$members[$compteur]["nom"]=strtoupper($resultat->fields[1]);
		$members[$compteur]["prenom"]=$resultat->fields[2];
		$members[$compteur]["id_adh"]=$resultat->fields[0];
		$members[$compteur]["pseudo"]=$resultat->fields[3];
		$members[$compteur]["statut"]=_T($resultat->fields[5]);
		$members[$compteur]["statut_cotis"]=$statut_cotis;
		$compteur++;
		$resultat->MoveNext();
	} 
	$resultat->Close();

	$tpl->assign('page_title', _T("Management of members"));
	$tpl->assign("error_detected",$error_detected);
	if(isset($warning_detected))
		$tpl->assign("warning_detected",$warning_detected);
	$tpl->assign("members",$members);
	$tpl->assign("nb_members",$nbadh->fields[0]);
	$tpl->assign("nb_pages",$nbpages);
	$tpl->assign("page",$page);
	$tpl->assign("numrows",$numrows);
	$tpl->assign('filtre_fld_options', array(
			0 => _T("Name"),
			1 => _T("Address"),
			2 => _T("Email,URL,IM"),
			3 => _T("Job"),
			4 => _T("Infos")));
	$tpl->assign('filtre_options', array(
			0 => _T("All members"),
			3 => _T("Members up to date"),
			1 => _T("Close expiries"),
			2 => _T("Latecomers"),
			4 => _T("Never contributed")));
	$tpl->assign('filtre_2_options', array(
			0 => _T("All the accounts"),
			1 => _T("Active accounts"),
			2 => _T("Inactive accounts")));
	$tpl->assign('nbshow_options', array(
			10 => "10",
			20 => "20",
			50 => "50",
			100 => "100",
			0 => _T("All")));
	$content = $tpl->fetch("gestion_adherents.tpl");
	$tpl->assign("content",$content);
	//$tpl->assign("pref", $pref);
	$tpl->display("page.tpl");
?>
