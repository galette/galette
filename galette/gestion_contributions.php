<? 
 
/* gestion_contributions.php
 * - Récapitulatif des contributions
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
	include(WEB_ROOT."includes/lang.inc.php"); 
	include(WEB_ROOT."includes/session.inc.php"); 
	
	$filtre_id_adh = "";
	
	if ($_SESSION["logged_status"]==0) 
		header("location: index.php");
	if ($_SESSION["admin_status"]==0) 
		$_SESSION["filtre_cotis_adh"] = $_SESSION["logged_id_adh"];
	else
	{
		if (is_numeric($_GET["id_adh"]))
			$_SESSION["filtre_cotis_adh"]=$_GET["id_adh"];
		else
			$_SESSION["filtre_cotis_adh"]="";
	}		
			

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

	include("header.php");

	if ($_SESSION["admin_status"]==1) 
	if (isset($_GET["sup"]))
	{
		// recherche adherent
		$requetesel = "SELECT id_adh
			    FROM cotisations 
			    WHERE id_cotis=".$DB->qstr($_GET["sup"]); 
		$result_adh = &$DB->Execute($requetesel);
		if (!$result_adh->EOF)
		{			
			$id_adh = $result_adh->fields["id_adh"];

			$requetesup = "SELECT nom_adh, prenom_adh FROM adherents WHERE id_adh=".$DB->qstr($id_adh);
			$resultat = $DB->Execute($requetesup);
			if (!$resultat->EOF)
			{			
				// supression record cotisation
				$requetesup = "DELETE FROM cotisations 
				    	    WHERE id_cotis=".$DB->qstr($_GET["sup"]); 
				$DB->Execute($requetesup);
			
				// mise a jour de l'échéance
				$date_fin = get_echeance($DB, $id_adh);
				if ($date_fin!="")
					$date_fin_update = $DB->DBDate(mktime(0,0,0,$date_fin[1],$date_fin[0],$date_fin[2]));
				else
					$date_fin_update = "NULL";	
				$requeteup = "UPDATE adherents
					    SET date_echeance=".$date_fin_update."
					    WHERE id_adh=".$DB->qstr($id_adh);
				$DB->Execute($requeteup);
 				dblog(_T("Suppression d'une contribution :")." ".strtoupper($resultat->fields[0])." ".$resultat->fields[1], $requetesup);							
 			}
 			$resultat->Close();
 		}
 		$result_adh->Close();
	}
?> 
		<H1 class="titre"><? echo _T("Gestion des contributions"); ?></H1>
<?
	$requete[0] = "SELECT cotisations.*, adherents.nom_adh, adherents.prenom_adh,
			types_cotisation.libelle_type_cotis
			FROM cotisations,adherents,types_cotisation
			WHERE cotisations.id_adh=adherents.id_adh
			AND types_cotisation.id_type_cotis=cotisations.id_type_cotis ";
	$requete[1] = "SELECT count(id_cotis)
			FROM cotisations ";

	// phase filtre
	
	if ($_SESSION["filtre_cotis_adh"]!="")
	{
		$requete[0] .= "AND cotisations.id_adh='" . $_SESSION["filtre_cotis_adh"] . "' ";
		$requete[1] .= "WHERE cotisations.id_adh='" . $_SESSION["filtre_cotis_adh"] . "' ";
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
	$requete[0] .= " cotisations.date_cotis ".$tri_cotis_sens_txt; 
	
	// $resultat = &$DB->Execute($requete[0]); 
	$resultat = &$DB->SelectLimit($requete[0],PREF_NUMROWS,($page-1)*PREF_NUMROWS);
	$nbcotis = &$DB->Execute($requete[1]); 
	
	if ($nbcotis->fields[0]%PREF_NUMROWS==0) 
		$nbpages = intval($nbcotis->fields[0]/PREF_NUMROWS);
	else 
		$nbpages = intval($nbcotis->fields[0]/PREF_NUMROWS)+1;
	$pagestring = "";
	if ($nbpages==0)
		$pagestring = "<b>1</b>";
	else for ($i=1;$i<=$nbpages;$i++)
	{
		if ($i!=$page)
			$pagestring .= "<a href=\"gestion_contributions.php?page=".$i."\">".$i."</a> ";
		else
			$pagestring .= $i." ";
	}

?>
						<TABLE id="infoline" width="100%">
							<TR>
								<TD class="left"><? echo $nbcotis->fields[0]." "; if ($nbcotis->fields[0]!=1) echo _T("contributions"); else echo _T("contribution"); ?></TD>
								<TD class="right"><? echo _T("Pages :"); ?> <SPAN class="pagelink"><? echo $pagestring; ?></SPAN></TD>
							</TR>
						</TABLE>
						<TABLE width="100%"> 
							<TR> 
								<TH width="15" class="listing">#</TH> 
			  					<TH class="listing left"> 
									<A href="gestion_contributions.php?tri=0&amp;id_adh=<? echo $_SESSION["filtre_cotis_adh"] ?>" class="listing"><? echo _T("Date"); ?></A>
									<?
										if ($_SESSION["tri_cotis"]=="0")
											if ($_SESSION["tri_cotis_sens"]=="0")
												echo "<IMG src=\"images/asc.png\" width=\"7\" height=\"7\" alt=\"\">";
											else 
												echo "<IMG src=\"images/desc.png\" width=\"7\" height=\"7\" alt=\"\">";
									?>
								</TH> 
<?
	if ($_SESSION["admin_status"]==1) 
	{
?>
								<TH class="listing left"> 
									<A href="gestion_contributions.php?tri=1&amp;id_adh=<? echo $_SESSION["filtre_cotis_adh"] ?>" class="listing"><? echo _T("Adhérent"); ?></A>
									<?
										if ($_SESSION["tri_cotis"]=="1")
											if ($_SESSION["tri_cotis_sens"]=="0")
												echo "<IMG src=\"images/asc.png\" width=\"7\" height=\"7\" alt=\"\">";
											else 
												echo "<IMG src=\"images/desc.png\" width=\"7\" height=\"7\" alt=\"\">";
									?>
								</TH> 
<?
	}
?>
								<TH class="listing left"> 
									<A href="gestion_contributions.php?tri=2&amp;id_adh=<? echo $_SESSION["filtre_cotis_adh"] ?>" class="listing"><? echo _T("Type"); ?></A>
<?
										if ($_SESSION["tri_cotis"]=="2")
											if ($_SESSION["tri_cotis_sens"]=="0")
												echo "<IMG src=\"images/asc.png\" width=\"7\" height=\"7\" alt=\"\">";
											else 
												echo "<IMG src=\"images/desc.png\" width=\"7\" height=\"7\" alt=\"\">";
									?>
								</TH> 
								<TH class="listing left"> 
									<A href="gestion_contributions.php?tri=3&amp;id_adh=<? echo $_SESSION["filtre_cotis_adh"] ?>" class="listing"><? echo _T("Montant"); ?></A>
									<?
										if ($_SESSION["tri_cotis"]=="3")
											if ($_SESSION["tri_cotis_sens"]=="0")
												echo "<IMG src=\"images/asc.png\" width=\"7\" height=\"7\" alt=\"\">";
											else 
												echo "<IMG src=\"images/desc.png\" width=\"7\" height=\"7\" alt=\"\">";
									?>
								</TH> 
								<TH class="listing left"> 
									<A href="gestion_contributions.php?tri=4&amp;id_adh=<? echo $_SESSION["filtre_cotis_adh"] ?>" class="listing"><? echo _T("Durée"); ?></A>
									<?
										if ($_SESSION["tri_cotis"]=="4")
											if ($_SESSION["tri_cotis_sens"]=="0")
												echo "<IMG src=\"images/asc.png\" width=\"7\" height=\"7\" alt=\"\">";
											else 
												echo "<IMG src=\"images/desc.png\" width=\"7\" height=\"7\" alt=\"\">";
									?>
								</TH> 
<?
	if ($_SESSION["admin_status"]==1) 
	{
?>
								<TH width="55" class="listing"> 
									<? echo _T("Actions"); ?> 
								</TH> 
<?
	}
?>
							</TR> 
<? 
	$compteur = 1+($page-1)*PREF_NUMROWS;
	$activity_class = "";
	if ($resultat->EOF)
	{
		if ($_SESSION["admin_status"]==1)
			$colspan = 7;
		else
			$colspan = 5;
?>
							<TR>
								<TD colspan="<? echo $colspan; ?>" class="emptylist"><? echo _T("aucune contribution"); ?></TD>
							</TR>
<?	
	}
	else while(!$resultat->EOF) 
	{ 
		if ($resultat->fields["duree_mois_cotis"]!="0")
			$row_class = "cotis-normal";
		else
			$row_class = "cotis-give";
?>							 
							<TR> 
								<TD width="15" class="<? echo $row_class; ?> center" nowrap><? echo $compteur ?></TD> 
								<TD width="50" class="<? echo $row_class; ?>" nowrap> 
									<?
										list($a,$m,$j)=split("-",$resultat->fields["date_cotis"]);
										echo "$j/$m/$a"; 
									?> 
								</TD> 
<?
	if ($_SESSION["admin_status"]==1) 
	{
?>
								<TD class="<? echo $row_class; ?>" nowrap> 
									<A href="voir_adherent.php?id_adh=<? echo $resultat->fields["id_adh"] ?>"><?
										echo strtoupper(htmlentities($resultat->fields["nom_adh"], ENT_QUOTES))." ";
										if (isset($resultat->fields["prenom_adh"]))
											echo htmlentities($resultat->fields["prenom_adh"], ENT_QUOTES);
									?></A> 
								</TD> 
<?
	}
?>
								<TD class="<? echo $row_class; ?>" nowrap><? echo _T($resultat->fields["libelle_type_cotis"]) ?></TD> 
								<TD class="<? echo $row_class; ?>" nowrap><? echo $resultat->fields["montant_cotis"] ?></TD> 
								<TD class="<? echo $row_class; ?>" nowrap><? echo $resultat->fields["duree_mois_cotis"] ?></TD> 
<?
	if ($_SESSION["admin_status"]==1) 
	{
?>
								<TD width="55" class="<? echo $row_class; ?> center" nowrap>  
									<A href="ajouter_contribution.php?id_cotis=<? echo $resultat->fields["id_cotis"] ?>"><IMG src="images/icon-edit.png" alt="<? echo _T("[mod]"); ?>" border="0" width="12" height="13"></A>
									<A onClick="return confirm('<? echo str_replace("\n","\\n",addslashes(_T("Voulez-vous vraiment supprimer cet contribution de la base ?"))); ?>')" href="gestion_contributions.php?sup=<? echo $resultat->fields["id_cotis"] ?>"><IMG src="images/icon-trash.png" alt="<? echo _T("[sup]"); ?>" border="0" width="11" height="13"></A>
								</TD> 
<?
	}

		$compteur++;
		$resultat->MoveNext();
	}
	$resultat->Close();
?>
						</TABLE>
						<DIV id="infoline2" class="right"><? echo _T("Pages :"); ?> <SPAN class="pagelink"><? echo $pagestring; ?></SPAN></DIV>
<?	
	// affichage du temps d'ahésion restant si on est en train de visualiser
	// les cotisations d'un membre unique
	
	if ($_SESSION["filtre_cotis_adh"]!="")
	{
		$requete = "SELECT date_echeance, bool_exempt_adh
			    FROM adherents
			    WHERE id_adh='".$_SESSION["filtre_cotis_adh"]."'";
		$resultat = $DB->Execute($requete);
		
		// temps d'adhésion
		if($resultat->fields[1])
		{
			$statut_cotis = _T("Exempt de cotisation");
			$color = "#DDFFDD";
		}
		else
		{
			if ($resultat->fields[0]=="")
			{
				$statut_cotis = _T("N'a jamais cotisé");
				$color = "#EEEEEE";			
			}
			else
			{
			
			
			$date_fin = split("-",$resultat->fields[0]);
			$ts_date_fin = mktime(0,0,0,$date_fin[1],$date_fin[2],$date_fin[0]);
			$aujourdhui = time();
			
			$difference = intval(($ts_date_fin - $aujourdhui)/(3600*24));
			if ($difference==0)
			{
				$statut_cotis = _T("Dernier jour !");
				$color = "#FFDDDD";
			}
			elseif ($difference<0)
			{
				$statut_cotis = _T("En retard de")." ".-$difference." "._T("jours")." ("._T("depuis le")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
				$color = "#FFDDDD";
			}
			else
			{
				$statut_cotis = $difference." "._T("jours restants")." ("._T("fin le")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
				if ($difference < 30)
					$color = "#FFE9AB";
				else
					$color = "#DDFFDD";	
			}
			
			}
		}		
		
		
		/*$days_left = get_days_left($DB, $_SESSION["filtre_cotis_adh"]);
		$cumul = $days_left["cumul"];
		$statut_cotis = $days_left["text"];
		$color = $days_left["color"];*/
	
		echo "<BR><DIV align=\"center\"><TABLE bgcolor=\"".$color."\"><TR><TD>".$statut_cotis."</TD></TR></TABLE></DIV>";
	}
	
?>							 

<? 
  include("footer.php"); 
?>
