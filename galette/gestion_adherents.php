<? 

/* gestion_adherents.php
 * - Récapitulatif des adhérents
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
 
	include("includes/config.inc.php"); 
	include(WEB_ROOT."includes/database.inc.php"); 
	include(WEB_ROOT."includes/functions.inc.php"); 
        include_once("includes/i18n.inc.php"); 
	include(WEB_ROOT."includes/lang.inc.php"); 
	include(WEB_ROOT."includes/session.inc.php"); 
	
	if ($_SESSION["logged_status"]==0) 
		header("location: index.php");
	if ($_SESSION["admin_status"]==0) 
		header("location: voir_adherent.php");
		
	$page = 1;
	if (isset($_GET["page"]))
		$page = $_GET["page"];

	if (isset($_GET["filtre"]))
		if (is_numeric($_GET["filtre"]))
			$_SESSION["filtre_adh"]=$_GET["filtre"];

	if (isset($_GET["filtre_2"]))
		if (is_numeric($_GET["filtre_2"]))
			$_SESSION["filtre_adh_2"]=$_GET["filtre_2"];
	
	// Tri
	
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
	

	include("header.php");

	if (isset($_GET["sup"]))
	{
		if (is_numeric($_GET["sup"]))
		{
			$requetesup = "SELECT nom_adh, prenom_adh FROM ".PREFIX_DB."adherents WHERE id_adh=".$DB->qstr($_GET["sup"]);
			$resultat = $DB->Execute($requetesup);
			if (!$resultat->EOF)
			{
				// supression record adhérent
				$requetesup = "DELETE FROM ".PREFIX_DB."adherents 
						WHERE id_adh=".$DB->qstr($_GET["sup"]); 
				$DB->Execute($requetesup); 		
	
				// suppression de l'eventuelle photo
				@unlink(WEB_ROOT . "photos/".$id_adh.".jpg");
				@unlink(WEB_ROOT . "photos/".$id_adh.".gif");
				@unlink(WEB_ROOT . "photos/".$id_adh.".jpg");
				@unlink(WEB_ROOT . "photos/tn_".$id_adh.".jpg");
				@unlink(WEB_ROOT . "photos/tn_".$id_adh.".gif");
				@unlink(WEB_ROOT . "photos/tn_".$id_adh.".jpg");
			
				// suppression records cotisations
				$requetesup = "DELETE FROM ".PREFIX_DB."cotisations 
						WHERE id_adh=" . $DB->qstr($_GET["sup"]); 
				$DB->Execute($requetesup); 			
				dblog(_("Delete the member card (and dues)")." ".strtoupper($resultat->fields[0])." ".$resultat->fields[1], $requetesup);
			}
			$resultat->Close();
 		}
	}

?> 
	<H1 class="titre"><? echo _("Management of members"); ?></H1>
<?
	// selection des adherents et application filtre / tri
		
	$requete[0] = "SELECT id_adh, nom_adh, prenom_adh, pseudo_adh, activite_adh,
		       libelle_statut, bool_exempt_adh, titre_adh, email_adh, bool_admin_adh, date_echeance
		       FROM ".PREFIX_DB."adherents, ".PREFIX_DB."statuts
		       WHERE ".PREFIX_DB."adherents.id_statut=".PREFIX_DB."statuts.id_statut ";
	$requete[1] = "SELECT count(id_adh)
		       FROM ".PREFIX_DB."adherents 
		       WHERE 1=1 ";
								
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

	// filtre d'affichage des adherents bientot a echeance
	if ($_SESSION["filtre_adh"]=="1")
	{
		$requete[0] .= "AND date_echeance > ".$DB->DBDate(time())."
			        AND date_echeance < ".$DB->OffsetDate(30)." ";
		$requete[1] .= "AND date_echeance > ".$DB->DBDate(time())."
			        AND date_echeance < ".$DB->OffsetDate(30)." ";
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
	elseif ($_SESSION["tri_adh"]=="3")
		$requete[0] .= "bool_exempt_adh ".$tri_adh_sens_txt.", date_echeance ".$tri_adh_sens_txt.",";

	// defaut : tri par nom, prenom
	$requete[0] .= "nom_adh ".$tri_adh_sens_txt.", prenom_adh ".$tri_adh_sens_txt; 
	
	$resultat = &$DB->SelectLimit($requete[0],PREF_NUMROWS,($page-1)*PREF_NUMROWS);
	$nbadh = &$DB->Execute($requete[1]);

	if ($nbadh->fields[0]%PREF_NUMROWS==0) 
		$nbpages = intval($nbadh->fields[0]/PREF_NUMROWS);
	else 
		$nbpages = intval($nbadh->fields[0]/PREF_NUMROWS)+1;
	$pagestring = "";
        if ($nbpages==0)
		$pagestring = "<b>1</b>";
	else for ($i=1;$i<=$nbpages;$i++)
	{
		if ($i!=$page)
			$pagestring .= "<A href=\"gestion_adherents.php?page=".$i."\">".$i."</A> ";
		else
			$pagestring .= $i." ";
	}
?>
	<DIV id="listfilter">
		<FORM action="gestion_adherents.php" method="get" name="filtre">
		 	<? echo _("Display:"); ?>&nbsp;
			<SELECT name="filtre" onChange="form.submit()">
				<OPTION value="0"<? isSelected("0",$_SESSION["filtre_adh"]) ?>><? echo _("All members"); ?></OPTION>
				<OPTION value="3"<? isSelected("3",$_SESSION["filtre_adh"]) ?>><? echo _("Members up to date"); ?></OPTION>
				<OPTION value="1"<? isSelected("1",$_SESSION["filtre_adh"]) ?>><? echo _("Close expiries"); ?></OPTION>
				<OPTION value="2"<? isSelected("2",$_SESSION["filtre_adh"]) ?>><? echo _("Latecomers"); ?></OPTION>
			</SELECT>
			<SELECT name="filtre_2" onChange="form.submit()">
				<OPTION value="0"<? isSelected("0",$_SESSION["filtre_adh_2"]) ?>><? echo _("All the accounts"); ?></OPTION>
				<OPTION value="1"<? isSelected("1",$_SESSION["filtre_adh_2"]) ?>><? echo _("Active accounts"); ?></OPTION>
				<OPTION value="2"<? isSelected("2",$_SESSION["filtre_adh_2"]) ?>><? echo _("Inactive accounts"); ?></OPTION>
			</SELECT>
			<INPUT type="submit" value="<? echo _("Filter"); ?>">
		</FORM>
	</DIV>
	<TABLE id="infoline" width="100%">
		<TR>
			<TD class="left"><? echo $nbadh->fields[0]." "; if ($nbadh->fields[0]!=1) echo _("members"); else echo _("member"); ?></TD>
			<TD class="right"><? echo _("Pages:"); ?> <SPAN class="pagelink"><? echo $pagestring; ?></SPAN></TD>
		</TR>
	</TABLE>
	<TABLE width="100%"> 
		<TR> 
			<TH width="15" class="listing">#</TH> 
  			<TH width="250" class="listing left"> 
				<A href="gestion_adherents.php?tri=0" class="listing"><? echo _("Name"); ?></A>
<?
	if ($_SESSION["tri_adh"]=="0")
	{
		if ($_SESSION["tri_adh_sens"]=="0")
			$img_sens = "asc.png";
		else
			$img_sens = "desc.png";
	}
	else
		$img_sens = "icon-empty.png";
?>
				<IMG src="images/<? echo $img_sens; ?>" width="7" height="7" alt="">
			</TH> 
			<TH class="listing left" nowrap> 
				<A href="gestion_adherents.php?tri=1" class="listing"><? echo _("Nickname"); ?></A>
<?
	if ($_SESSION["tri_adh"]=="1")
	{
		if ($_SESSION["tri_adh_sens"]=="0")
			$img_sens = "asc.png";
		else
			$img_sens = "desc.png";
	}
	else
		$img_sens = "icon-empty.png";
?>
				<IMG src="images/<? echo $img_sens; ?>" width="7" height="7" alt="">
			</TH> 
			<TH class="listing left"> 
				<A href="gestion_adherents.php?tri=2" class="listing"><? echo _("Status"); ?></A>
<?
	if ($_SESSION["tri_adh"]=="2")
	{
		if ($_SESSION["tri_adh_sens"]=="0")
			$img_sens = "asc.png";
		else
			$img_sens = "desc.png";
	}
	else
		$img_sens = "icon-empty.png";
?>
				<IMG src="images/<? echo $img_sens; ?>" width="7" height="7" alt="">
			</TH> 
			<TH class="listing left"> 
				<A href="gestion_adherents.php?tri=3" class="listing"><? echo _("State of dues"); ?></A>
<?
	if ($_SESSION["tri_adh"]=="3")
	{
		if ($_SESSION["tri_adh_sens"]=="0")
			$img_sens = "asc.png";
		else
			$img_sens = "desc.png";
	}
	else
		$img_sens = "icon-empty.png";
?>
				<IMG src="images/<? echo $img_sens; ?>" width="7" height="7" alt="">
			</TH> 
			<TH width="55" class="listing"><? echo _("Actions"); ?></TH> 
		</TR> 
<? 
	$compteur = 1+($page-1)*PREF_NUMROWS;
	if ($resultat->EOF)
	{
?>	
		<TR><TD colspan="6" class="emptylist"><? echo _("no member"); ?></TD></TR>
<?
	}
	else while (!$resultat->EOF) 
	{ 
		// définition CSS pour adherent désactivé
		if ($resultat->fields[4]=="1")
			$row_class = "actif";
		else
			$row_class = "inactif";
			
		// temps d'adhésion
		if($resultat->fields[6])
		{
			$statut_cotis = _("Freed of dues");
			$row_class .= " cotis-exempt";
		}
		else
		{
			if ($resultat->fields[10]=="")
			{
				$statut_cotis = _("Never contributed");
				$row_class .= " cotis-never";
			}
			else
			{
				$date_fin = split("-",$resultat->fields[10]);
				$ts_date_fin = mktime(0,0,0,$date_fin[1],$date_fin[2],$date_fin[0]);
				$aujourdhui = time();
				
				$difference = intval(($ts_date_fin - $aujourdhui)/(3600*24));
				if ($difference==0)
				{
					$statut_cotis = _("Last day!");
					$row_class .= " cotis-lastday";
				}
				elseif ($difference<0)
				{
					$statut_cotis = _("Late of ").-$difference." "._("days")." ("._("since")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
					$row_class .= " cotis-late";
				}
				else
				{
					if ($difference!=1)
						$statut_cotis = $difference." "._("days remaining")." ("._("ending on")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
					else
						$statut_cotis = $difference." "._("day remaining")." ("._("ending on")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
					if ($difference < 30)
						$row_class .= " cotis-soon";
					else
						$row_class .= " cotis-ok";	
				}				
			}
		}
?>							 
		<TR>
			<TD width="15" class="<? echo $row_class ?>"><? echo $compteur ?></TD> 
			<TD class="<? echo $row_class ?>" nowrap>
<?
		if ($resultat->fields[7]=="1") {
?>
				<IMG src="images/icon-male.png" Alt="<? echo _("[M]"); ?>" align="middle" width="10" height="12">
<?
		} else {
?>
				<IMG src="images/icon-female.png" Alt="<? echo _("[W]"); ?>" align="middle" width="9" height="12">
<?
		}
		if ($resultat->fields[8]!="") {
?>
				<A href="mailto:<? echo $resultat->fields[8] ?>"><IMG src="images/icon-mail.png" Alt="<? echo _("[Mail]"); ?>" align="middle" border="0" width="14" height="10"></A>
<?
		} else {
?>
				<IMG src="images/icon-empty.png" Alt="" align="middle" border="0" width="14" height="10">
<?
		}
		if ($resultat->fields[9]=="1") {
?>
				<IMG src="images/icon-star.png" Alt="<? echo _("[admin]"); ?>" align="middle" width="12" height="13">
<?
		}	else {
?>
				<IMG src="images/icon-empty.png" Alt="" align="middle" width="12" height="13">
<?
		}
?>
				<A href="voir_adherent.php?id_adh=<? echo $resultat->fields["id_adh"] ?>"><? echo htmlentities(strtoupper($resultat->fields[1]),ENT_QUOTES)." ".htmlentities($resultat->fields[2], ENT_QUOTES) ?></A>
			</TD> 
			<TD class="<? echo $row_class ?>" nowrap><? echo htmlentities($resultat->fields[3], ENT_QUOTES) ?></TD> 
			<TD class="<? echo $row_class ?>" nowrap><? echo _($resultat->fields[5]) ?></TD> 
			<TD class="<? echo $row_class ?>" nowrap><? echo $statut_cotis ?></TD>
			<TD class="<? echo $row_class ?> center"> 
				<A href="ajouter_adherent.php?id_adh=<? echo $resultat->fields[0] ?>"><IMG src="images/icon-edit.png" alt="<? echo _("[mod]"); ?>" border="0" width="12" height="13"></A>
				<A href="gestion_contributions.php?id_adh=<? echo $resultat->fields[0] ?>"><IMG src="images/icon-money.png" alt="<? echo _("[$]"); ?>" border="0" width="13" height="13"></A>
				<A onClick="return confirm('<? echo str_replace("\n","\\n",addslashes(_("Do you really want to delete this member from the base, this will delete also the history of her fees. To avoid this you can just unactivate her account.\n\nDo you still want to delete this member ?"))); ?>')" href="gestion_adherents.php?sup=<? echo $resultat->fields[0] ?>"><IMG src="images/icon-trash.png" alt="<? echo _("[del]"); ?>" border="0" width="11" height="13"></A>
			</TD> 
		</TR> 
<? 
		$compteur++;
		$resultat->MoveNext();
	} 
	$resultat->Close();
?>							 
	</TABLE>
	<DIV id="infoline2" class="right"><? echo _("Pages:"); ?> <SPAN class="pagelink"><? echo $pagestring; ?></SPAN></DIV>
<? 
  include("footer.php"); 
?>
