<? 

/* mailing_adherents.php
 * - Envoi de mails en masse
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
	include(WEB_ROOT."includes/session.inc.php"); 
	include(WEB_ROOT."includes/functions.inc.php"); 
        include_once("includes/i18n.inc.php"); 
	//include(WEB_ROOT."includes/lang.inc.php"); 
	
	if ($_SESSION["logged_status"]==0) 
		header("location: index.php");
	if ($_SESSION["admin_status"]==0) 
		header("location: voir_adherent.php");
		
	$mailing_adh = array();
	$nomail_adh = array();
	if (isset($_POST["mailing_adh"]))
		while (list($key,$value)=each($_POST["mailing_adh"]))
			$mailing_adh[]=$value;

	$mailing_corps = "";
	if (isset($_POST["mailing_corps"]))
		$mailing_corps = stripslashes($_POST["mailing_corps"]);

	$mailing_objet = "";
	if (isset($_POST["mailing_objet"]))
		$mailing_objet = stripslashes($_POST["mailing_objet"]);

	$error_detected = "";
	
	$etape = 0;
	
	if (isset($_POST["mailing_go"]))
	{
		if ($mailing_objet=="")
			$error_detected .= "<LI>"._T("Please type an object for the message.")."</LI>";

		if ($mailing_corps=="")
			$error_detected .= "<LI>"._T("Please enter a message.")."</LI>";
			
		if (!isset($_POST["mailing_adh"]))
			$error_detected .= "<LI>"._T("Please select at least one member.")."</LI>";

		if ($error_detected=="")
			$etape = 1;
	}	

	include("header.php");

	if ($etape==0)
	{
		
		if (isset($_GET["filtre_2"]))
			if (is_numeric($_GET["filtre_2"]))
				$_SESSION["filtre_adh_2"]=$_GET["filtre_2"];
	
		if (isset($_GET["filtre"]))
			if (is_numeric($_GET["filtre"]))
				$_SESSION["filtre_adh"]=$_GET["filtre"];
	
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
		
		$etiquettes = 0;
		if (isset($_GET["etiquettes"]))
			$etiquettes = $_GET["etiquettes"];
		elseif (isset($_POST["etiquettes"]))
			$etiquettes = $_POST["etiquettes"];
		
		if ($etiquettes==1)
		{
?> 
			<H1 class="titre"><? echo _T("Generate labels"); ?></H1>
<?
		}
		else
		{	
?> 
			<H1 class="titre"><? echo _T("Mailing"); ?></H1>
<?
		}
		
		// Affichage des erreurs
		if ($error_detected!="")
		{
?>
		  	<DIV id="errorbox">
		  		<H1><? echo _T("- ERROR -"); ?></H1>
		  		<UL>
		  			<? echo $error_detected; ?>
		  		</UL>
		  	</DIV>
<?
		}

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
		if ($_SESSION["filtre_adh_2"]=="2")
		{
			$requete[0] .= "AND ".PREFIX_DB."adherents.activite_adh='0' ";
			$requete[1] .= "AND ".PREFIX_DB."adherents.activite_adh='0' ";
		}

		// filtre d'affichage des adherents à jour
		if ($_SESSION["filtre_adh"]=="3")
		{
			$requete[0] .= "AND (date_echeance > ".$DB->DBDate(time())." OR bool_exempt_adh='1') ";
			$requete[1] .= "AND (date_echeance > ".$DB->DBDate(time())." OR bool_exempt_adh='1') ";
		}

		// filtre d'affichage des adherents retardataires
		if ($_SESSION["filtre_adh"]=="2")
		{
			$requete[0] .= "AND date_echeance < ".$DB->DBDate(time())." ";
			$requete[1] .= "AND date_echeance < ".$DB->DBDate(time())." ";
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
		
		$resultat = &$DB->Execute($requete[0]);
		$nbadh = &$DB->Execute($requete[1]);
?>
		<SCRIPT LANGUAGE="JavaScript">
		<!--
		var checked = 1; 	
		function check()
		{ 
			for (var i=0;i<document.mailing_form.elements.length;i++)
			{
				var e = document.mailing_form.elements[i];
				if(e.type == "checkbox")
				{
					e.checked = checked;
				}
			}
			checked = !checked;
		}
		-->
		</SCRIPT>
		<TABLE id="infoline" width="100%">
			<TR>
				<TD class="left"><? echo $nbadh->fields[0]." "; if ($nbadh->fields[0]!=1) echo _T("members"); else echo _T("member"); ?></TD>
				<TD class="right">
					<DIV id="listfilter">
						<FORM action="mailing_adherents.php" method="get" name="filtre">
						 	<? echo _T("Display:"); ?>&nbsp;
							<SELECT name="filtre" onChange="form.submit()">
								<OPTION value="0"<? isSelected("0",$_SESSION["filtre_adh"]) ?>><? echo _T("All members"); ?></OPTION>
								<OPTION value="3"<? isSelected("3",$_SESSION["filtre_adh"]) ?>><? echo _T("Members up to date"); ?></OPTION>
								<OPTION value="1"<? isSelected("1",$_SESSION["filtre_adh"]) ?>><? echo _T("Close expiries"); ?></OPTION>
								<OPTION value="2"<? isSelected("2",$_SESSION["filtre_adh"]) ?>><? echo _T("Latecomers"); ?></OPTION>
							</SELECT>
							<SELECT name="filtre_2" onChange="form.submit()">
								<OPTION value="0"<? isSelected("0",$_SESSION["filtre_adh_2"]) ?>><? echo _T("All the accounts"); ?></OPTION>
								<OPTION value="1"<? isSelected("1",$_SESSION["filtre_adh_2"]) ?>><? echo _T("Active accounts"); ?></OPTION>
								<OPTION value="2"<? isSelected("2",$_SESSION["filtre_adh_2"]) ?>><? echo _T("Inactive accounts"); ?></OPTION>
							</SELECT>
							<INPUT type="hidden" name="etiquettes" value="<? echo $etiquettes; ?>"> 
							<INPUT type="submit" value="<? echo _T("Filter"); ?>">
						</FORM>
					</DIV>
				</TD>
			</TR>
		</TABLE>
<?
		if ($etiquettes==1)
		{
?>
						<FORM action="etiquettes_adherents.php" method="post" name="mailing_form" target="_blank">
<?
		}
		else
		{
?>
						<FORM action="mailing_adherents.php" method="post" name="mailing_form">
<?
		}
?>
						<table width="100%"> 
							<TR> 
							<TH class="listing" width="15">#</TH> 
				  			<TH class="listing left" width="250"> 
									<A href="mailing_adherents.php?tri=0" class="listing"><? echo _T("Name"); ?></A>
									<?
										if ($_SESSION["tri_adh"]=="0")
											if ($_SESSION["tri_adh_sens"]=="0")
												echo "<IMG src=\"images/asc.png\" width=\"7\" height=\"7\" alt=\"\">";
											else 
												echo "<IMG src=\"images/desc.png\" width=\"7\" height=\"7\" alt=\"\">";
									?>
								</TH> 
								<TH class="listing left"> 
									<A href="mailing_adherents.php?tri=1" class="listing"><? echo _T("E-Mail"); ?></A>
									<?
										if ($_SESSION["tri_adh"]=="1")
											if ($_SESSION["tri_adh_sens"]=="0")
												echo "<IMG src=\"images/asc.png\" width=\"7\" height=\"7\" alt=\"\">";
											else 
												echo "<IMG src=\"images/desc.png\" width=\"7\" height=\"7\" alt=\"\">";
									?>
								</TH> 
								<TH class="listing left"> 
									<A href="mailing_adherents.php?tri=2" class="listing"><? echo _T("Status"); ?></A>
									<?
										if ($_SESSION["tri_adh"]=="2")
											if ($_SESSION["tri_adh_sens"]=="0")
												echo "<IMG src=\"images/asc.png\" width=\"7\" height=\"7\" alt=\"\">";
											else 
												echo "<IMG src=\"images/desc.png\" width=\"7\" height=\"7\" alt=\"\">";
									?>
								</TH> 
								<TH class="listing left"> 
									<A href="mailing_adherents.php?tri=3" class="listing"><? echo _T("State of dues"); ?></A>
									<?
										if ($_SESSION["tri_adh"]=="3")
											if ($_SESSION["tri_adh_sens"]=="0")
												echo "<IMG src=\"images/asc.png\" width=\"7\" height=\"7\" alt=\"\">";
											else 
												echo "<IMG src=\"images/desc.png\" width=\"7\" height=\"7\" alt=\"\">";
									?>
								</TH> 
								<TH width="55" class="listing">Actions</TH> 
							</tr> 
<? 
		if ($resultat->EOF)
		{
?>	
							<tr>
								<td colspan="6" class="emptylist"><? echo _T("no member"); ?></td>
							</tr>
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
			$statut_cotis = _T("Freed of dues");
			$row_class .= " cotis-exempt";
		}
		else
		{
			if ($resultat->fields[10]=="")
			{
				$statut_cotis = _T("Never contributed");
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
					$statut_cotis = $difference." "._T("days remaining")." ("._T("ending on")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
					if ($difference < 30)
						$row_class .= " cotis-soon";
					else
						$row_class .= " cotis-ok";	
				}				
			}
		}
?>							 
							<TR> 
								<TD width="15" class="<? echo $row_class; ?>" nowrap> 
									<INPUT type="checkbox" name="mailing_adh[]" value="<? echo $resultat->fields[0] ?>" <? if (in_array($resultat->fields[0],$mailing_adh)) echo "CHECKED"; ?>> 
								</TD> 
								<TD class="<? echo $row_class; ?>" nowrap>
<?
			if ($resultat->fields[7]=="1") {
?>
									<IMG src="images/icon-male.png" Alt="<? echo _T("[M]"); ?>" align="middle" width="10" height="12">
<?
			} else {
?>
									<IMG src="images/icon-female.png" Alt="<? echo _T("[W]"); ?>" align="middle" width="9" height="12">
<?
			}
?>
<?
			if ($resultat->fields[9]=="1") {
?>
									<IMG src="images/icon-star.png" Alt="<? echo _T("[admin]"); ?>" align="middle" width="12" height="13">
<?
			}	else {
?>
									<IMG src="images/icon-empty.png" Alt="" align="middle" width="12" height="13">
<?
			}
?>
									<A href="voir_adherent.php?id_adh=<? echo $resultat->fields["id_adh"] ?>"><? echo htmlentities(strtoupper($resultat->fields[1]), ENT_QUOTES)." ".htmlentities($resultat->fields[2], ENT_QUOTES); ?></A>
								</TD> 
								<TD class="<? echo $row_class; ?>" nowrap> 
									<? if ($resultat->fields[8]!="") echo "<A href=\"mailto:".htmlentities($resultat->fields[8], ENT_QUOTES)."\">".htmlentities($resultat->fields[8], ENT_QUOTES)."</A>"; ?>&nbsp; 
								</TD> 
								<TD class="<? echo $row_class; ?>" nowrap><? echo _T($resultat->fields[5]) ?></TD> 
								<TD class="<? echo $row_class; ?>" nowrap><? echo $statut_cotis ?></TD>
								<TD width="55" class="<? echo $row_class; ?> center"> 
									<A href="ajouter_adherent.php?id_adh=<? echo $resultat->fields[0] ?>"><IMG src="images/icon-edit.png" alt="<? echo _T("[mod]"); ?>" border="0" width="12" height="13"></A>
									<A href="gestion_contributions.php?id_adh=<? echo $resultat->fields[0] ?>"><IMG src="images/icon-money.png" alt="<? echo _T("[$]"); ?>" border="0" width="13" height="13"></A>
									<A onClick="return confirm('<? echo str_replace("\n","\\n",addslashes(_T("Do you really want to delete this member from the base, this will delete also the history of her fees. To avoid this you can just unactivate her account.\n\nDo you still want to delete this member ?"))); ?>')" href="gestion_adherents.php?sup=<? echo $resultat->fields[0] ?>"><IMG src="images/icon-trash.png" alt="<? echo _T("[del]"); ?>" border="0" width="11" height="13"></A>
								</TD> 
							</TR> 
<? 
			$resultat->MoveNext();
		} 
		$resultat->Close();
?>							 
						</TABLE>
						<A href="#" onClick="check()"><? echo _T("[ Select / unselect all ]"); ?></A>
						<BR>
						<BR>
<?
		if ($etiquettes==1)
		{
?>
							<DIV align="center"><INPUT type="submit" value="<? echo _T("Generate labels"); ?>"></DIV>
<?
		}
		else
		{
?>
						<DIV align="center">
						<TABLE border="0" id="input-table">
							<TR>
								<TH id="libelle"><? echo _T("Object:"); ?></TH>
							</TR>
							<TR>
								<TD><INPUT type="text" name="mailing_objet" value="<? echo htmlentities($mailing_objet, ENT_QUOTES); ?>" size="80"></TD>
							</TR>
							<TR>
								<TH id="libelle"><? echo _T("Message:"); ?></TH>
							</TR>
							<TR>
								<TD><TEXTAREA name="mailing_corps" cols="72" rows="15"><? echo htmlentities($mailing_corps, ENT_QUOTES); ?></TEXTAREA></TD>
							</TR>
							<TR>
								<TH align="center">
									<BR>
									<INPUT type="submit" value="<? echo _T("Preview"); ?>">
								</TH>
							</TR>
						</TABLE>
						</DIV>
<?
		}
?>
						<INPUT type="hidden" name="mailing_go" value="1">
						</FORM>
<? 
	}
	else
	{
		$confirm_detected="";
		
		// $mailing_corps = $_POST["mailing_corps"];
		// adhérents avec email
		$requete = "SELECT id_adh, nom_adh, prenom_adh, pseudo_adh, activite_adh,
				libelle_statut, bool_exempt_adh, titre_adh, email_adh, bool_admin_adh, date_echeance
				FROM ".PREFIX_DB."adherents, ".PREFIX_DB."statuts
	  				WHERE ".PREFIX_DB."adherents.id_statut=".PREFIX_DB."statuts.id_statut AND (";
		$where_clause = "";
		while(list($key,$value)=each($mailing_adh))
		{
			if ($where_clause!="")
				$where_clause .= " OR ";
			$where_clause .= "id_adh='".$value."'";
		}
		$requete .= $where_clause.") AND email_adh IS NOT NULL ORDER by nom_adh, prenom_adh;";
		$resultat = &$DB->Execute($requete);

		// adhérents sans email
		$requete = "SELECT id_adh, nom_adh, prenom_adh, adresse_adh, activite_adh,
				libelle_statut, bool_exempt_adh, titre_adh, cp_adh, bool_admin_adh, date_echeance,
				ville_adh, tel_adh, gsm_adh, msn_adh, icq_adh, pays_adh, jabber_adh, adresse2_adh
				FROM ".PREFIX_DB."adherents, ".PREFIX_DB."statuts
			       	WHERE ".PREFIX_DB."adherents.id_statut=".PREFIX_DB."statuts.id_statut AND (";
		$requete .= $where_clause.") AND email_adh IS NULL ORDER by nom_adh, prenom_adh;";
		$resultat_adh_nomail = &$DB->Execute($requete);
        
		if (isset($_POST["mailing_confirmed"]) && $resultat_adh_nomail->EOF==false)
            $confirm_detected = _T("Don't forget to contact the members who don't have an email address by another way.");
?>
			<H1 class="titre"><? echo _T("Mailing"); ?> <? if (isset($_POST["mailing_confirmed"])) echo _T("done!"); else echo _T("(preview)"); ?></H1>
<?
		// Affichage des erreurs
		if ($confirm_detected!="")
	  	echo "			<BR><DIV align=\"center\"><TABLE><TR><TD style=\"background: #DDFFDD; color: #FF0000\"><B><DIV align=\"center\">"._T("- WARNING -")."</DIV></B>" . $confirm_detected . "</TD></TR></TABLE></DIV>";
?>
			<BR>
			<B><? echo _T("Recipients of the mailing:"); ?></B>
			<TABLE width="100%"> 
				<TR> 
	  				<TH class="listing left" width="250"><? echo _T("Name"); ?></TH> 
					<TH class="listing left"><? echo _T("E-Mail"); ?></TH> 
					<TH class="listing left"> <? echo _T("Status"); ?></TH> 
					<TH class="listing left"><? echo _T("State of dues"); ?></TH> 
				</TR> 			
<?		
		$num_mails = 0;
		$concatmail = "";
		if ($resultat->EOF)
		{
?>	
				<tr>
					<td colspan="4" bgcolor="#EEEEEE" align="center"><i><? echo _T("no member"); ?></i></td>
				</tr>
<?
		}
		else while (!$resultat->EOF) 
		{
			if (isset($_POST["mailing_confirmed"]))
			{
				mail ($resultat->fields[8], $mailing_objet, $mailing_corps,"From: ".PREF_EMAIL_NOM." <".PREF_EMAIL.">\nContent-Type: text/plain; charset=iso-8859-15\n");
				$concatmail = $concatmail . " " . $resultat->fields[8];
				$num_mails++;
			}		

			// définition CSS pour adherent désactivé
			if ($resultat->fields[4]=="1")
				$activity_class = "";
			else
				$activity_class = " class=\"inactif\"";
				
			// temps d'adhésion
			if($resultat->fields[6])
			{
				$statut_cotis = _T("Freed of dues");
				$color = "#DDFFDD";
			}
			else
			{
				if ($resultat->fields[10]=="")
				{
					$statut_cotis = _T("Never contributed");
					$color = "#EEEEEE";			
				}
				else
				{
					$date_fin = split("-",$resultat->fields[10]);
					$ts_date_fin = mktime(0,0,0,$date_fin[1],$date_fin[2],$date_fin[0]);
					$aujourdhui = time();
					
					$difference = intval(($ts_date_fin - $aujourdhui)/(3600*24));
					if ($difference==0)
					{
						$statut_cotis = _T("Last day!");
						$color = "#FFDDDD";
					}
					elseif ($difference<0)
					{
						$statut_cotis = _T("Late of ").-$difference." "._T("days")." ("._T("since")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
						$color = "#FFDDDD";
					}
					else
					{
						$statut_cotis = $difference." "._T("days remaining")." ("._T("ending on")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
						if ($difference < 30)
							$color = "#FFE9AB";
						else
							$color = "#DDFFDD";	
					}					
				}
			}
		
?>							 
				<tr> 
					<td bgcolor="<? echo $color ?>"<? echo $activity_class ?>>
<?
			if ($resultat->fields[7]=="1") {
?>
						<img src="images/icon-male.png" Alt="<? echo _T("[M]"); ?>" align="middle" width="10" height="12">
<?
			} else {
?>
						<img src="images/icon-female.png" Alt="<? echo _T("[W]"); ?>" align="middle" width="9" height="12">
<?
			}
?>
<?
			if ($resultat->fields[9]=="1") {
?>
						<img src="images/icon-star.png" Alt="<? echo _T("[admin]"); ?>" align="middle" width="12" height="13">
<?
			}	else {
?>
						<img src="images/icon-empty.png" Alt="" align="middle" width="12" height="13">
<?
			}
?>
						<a href="voir_adherent.php?id_adh=<? echo $resultat->fields["id_adh"] ?>"><? echo htmlentities(strtoupper($resultat->fields[1]), ENT_QUOTES)." ".htmlentities($resultat->fields[2], ENT_QUOTES) ?></a>
					</td> 
					<td bgcolor="<? echo $color ?>"<? echo $activity_class ?>> 
						<? if ($resultat->fields[8]!="") echo "<A href=\"mailto:".htmlentities($resultat->fields[8], ENT_QUOTES)."\">".htmlentities($resultat->fields[8], ENT_QUOTES)."</A>"; ?>&nbsp; 
					</td> 
					<td bgcolor="<? echo $color ?>"<? echo $activity_class ?>>
						<? echo _T($resultat->fields[5]) ?> 
					</td> 
					<td bgcolor="<? echo $color ?>"<? echo $activity_class ?>> 
						<? echo $statut_cotis ?>
					</td>
				</TR>

<?	
			$resultat->MoveNext();
		}
		
		if (isset($_POST["mailing_confirmed"]))
			dblog(_T("Send of a mailing titled:")." \"".$mailing_objet."\" - ".$num_mails." "._T("recipients"), $concatmail."\n".$mailing_corps);
		
		$resultat->Close();
?>
			</TABLE>
<?
		if (!isset($_POST["mailing_confirmed"]))
		{
?>
			<DIV id="mailing_preview">
				<TABLE border="0" id="input-table" style="width: 100%;">
				<TR><TH id="libelle"><? echo _T("Object:"); ?></TH></TR>
				<TR><TD><? echo htmlentities($mailing_objet, ENT_QUOTES); ?></TD></TR>
				<TR><TH id="libelle"><? echo _T("Message:"); ?></TH></TR>
				<TR><TD style="height: 200px; vertical-align: top;"><? echo nl2br(htmlentities($mailing_corps, ENT_QUOTES)); ?></TD></TR>
				</TABLE>
			</DIV>
<?
		}
?>
						<DIV align="center">
						<TABLE>
							<TR>
<?
		if (!isset($_POST["mailing_confirmed"]))
		{
?>
								<TD>
									<FORM action="mailing_adherents.php" method="post">
<?
			reset($mailing_adh);
			while(list($key,$value)=each($mailing_adh))
			{
				echo "<INPUT type=\"hidden\" name=\"mailing_adh[]\" value=\"".$value."\">";
			}
?>
										<INPUT type ="hidden" name="mailing_corps" value="<? echo htmlentities($mailing_corps, ENT_QUOTES); ?>">
										<INPUT type ="hidden" name="mailing_objet" value="<? echo htmlentities($mailing_objet, ENT_QUOTES); ?>">
										<INPUT type ="hidden" name="mailing_confirmed" value="1">
										<INPUT type ="hidden" name="mailing_go" value="1">
										&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="<? echo _T("Send"); ?>">
									</FORM>
								</TD>
<?
		}
?>								
							<TR>
						</TABLE>
						</DIV>
						<BR>
			<B><? echo _T("Members who can't be reachable by e-mail:"); ?></B>
			<TABLE width="100%"> 
				<TR> 
	  				<TH class="listing left" width="250"><? echo _T("Name"); ?></TH> 
					<TH class="listing left"><? echo _T("Profile"); ?></TH> 
					<TH class="listing left"> <? echo _T("Status"); ?></TH> 
					<TH class="listing left"><? echo _T("State of dues"); ?></TH> 
				</TR> 			
<?
		if ($resultat_adh_nomail->EOF)
		{
?>	
							<tr>
								<td colspan="4" bgcolor="#EEEEEE" align="center"><i><? echo _T("no member"); ?></i></td>
							</tr>
<?
		}
		else while (!$resultat_adh_nomail->EOF) 
			{
				// définition CSS pour adherent désactivé
				if ($resultat_adh_nomail->fields[4]=="1")
					$activity_class = "";
				else
					$activity_class = " class=\"inactif\"";
					
				// temps d'adhésion
				if($resultat_adh_nomail->fields[6])
				{
					$statut_cotis = _T("Freed of dues");
					$color = "#DDFFDD";
				}
				else
				{
					if ($resultat_adh_nomail->fields[10]=="")
					{
						$statut_cotis = _T("Never contributed");
						$color = "#EEEEEE";			
					}
					else
					{
						$date_fin = split("-",$resultat_adh_nomail->fields[10]);
						$ts_date_fin = mktime(0,0,0,$date_fin[1],$date_fin[2],$date_fin[0]);
						$aujourdhui = time();
						
						$difference = intval(($ts_date_fin - $aujourdhui)/(3600*24));
						if ($difference==0)
						{
							$statut_cotis = _T("Last day!");
							$color = "#FFDDDD";
						}
						elseif ($difference<0)
						{
							$statut_cotis = _T("Late of ").-$difference." "._T("days")." ("._T("since")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
							$color = "#FFDDDD";
						}
						else
						{
							$statut_cotis = $difference." "._T("days remaining")." ("._T("ending on")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
							if ($difference < 30)
								$color = "#FFE9AB";
							else
								$color = "#DDFFDD";	
						}					
					}
				}
			
?>							 
							<tr> 
								<td valign="top" bgcolor="<? echo $color ?>"<? echo $activity_class ?>>
<?
				if ($resultat_adh_nomail->fields[7]=="1") {
?>
									<img src="images/icon-male.png" Alt="<? echo _T("[M]"); ?>" align="middle" width="10" height="12">
<?
				} else {
?>
									<img src="images/icon-female.png" Alt="<? echo _T("[W]"); ?>" align="middle" width="9" height="12">
<?
				}
?>
<?
				if ($resultat_adh_nomail->fields[9]=="1") {
?>
									<img src="images/icon-star.png" Alt="<? echo _T("[admin]"); ?>" align="middle" width="12" height="13">
<?
				}	else {
?>
									<img src="images/icon-empty.png" Alt="" align="middle" width="12" height="13">
<?
				}
?>
									<a href="voir_adherent.php?id_adh=<? echo $resultat_adh_nomail->fields["id_adh"] ?>"><? echo htmlentities(strtoupper($resultat_adh_nomail->fields[1]), ENT_QUOTES)." ".htmlentities($resultat_adh_nomail->fields[2], ENT_QUOTES); ?></a>
								</td> 
<?
				$coord_adh = "<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\">";
				$adresse_adh = "";
				if ($resultat_adh_nomail->fields[3]!="")
					$adresse_adh .= htmlentities($resultat_adh_nomail->fields[3], ENT_QUOTES);
				if ($resultat_adh_nomail->fields[8]!="") 
				{	
					if ($adresse_adh!="")
						$adresse_adh .= "<BR>";
					$adresse_adh .= htmlentities($resultat_adh_nomail->fields[8], ENT_QUOTES);
				}
				if ($resultat_adh_nomail->fields[11]!="") 
				{	
					if ($adresse_adh!="")
						$adresse_adh .= "<BR>";
					$adresse_adh .= htmlentities($resultat_adh_nomail->fields[11], ENT_QUOTES);
				}
				if ($resultat_adh_nomail->fields[16]!="") 
				{	
					if ($adresse_adh!="")
						$adresse_adh .= "<BR>";
					$adresse_adh .= htmlentities($resultat_adh_nomail->fields[16], ENT_QUOTES);
				}
				if ($resultat_adh_nomail->fields[18]!="") 
				{	
					if ($adresse_adh!="")
						$adresse_adh .= "<BR>";
					$adresse_adh .= htmlentities($resultat_adh_nomail->fields[18], ENT_QUOTES);
				}
				if ($adresse_adh!="")
					$coord_adh .= "<tr><td width=\"10\" valign=\"top\"><B>".str_replace(" ","&nbsp;",_T("Address:"))."</B>&nbsp;</td><td>".$adresse_adh."</td></tr>";
				if ($resultat_adh_nomail->fields[12]!="") 
					$coord_adh .= "<tr><td style=\"padding-right: 1px;\"><B>".str_replace(" ","&nbsp;",_T("Phone:"))."</B>&nbsp;</td><td>".htmlentities($resultat_adh_nomail->fields[12], ENT_QUOTES)."</td></tr>";
				if ($resultat_adh_nomail->fields[13]!="") 
					$coord_adh .= "<tr><td><B>".str_replace(" ","&nbsp;",_T("Mobile phone:"))."</B>&nbsp;</td><td>".htmlentities($resultat_adh_nomail->fields[13], ENT_QUOTES)."</td></tr>";
				if ($resultat_adh_nomail->fields[15]!="") 
					$coord_adh .= "<tr><td><B>".str_replace(" ","&nbsp;",_T("ICQ:"))."</B>&nbsp;</td><td>".htmlentities($resultat_adh_nomail->fields[15], ENT_QUOTES)."</td></tr>";
				if ($resultat_adh_nomail->fields[17]!="") 
					$coord_adh .= "<tr><td><B>".str_replace(" ","&nbsp;",_T("Jabber:"))."</B>&nbsp;</td><td>".htmlentities($resultat_adh_nomail->fields[17], ENT_QUOTES)."</td></tr>";
				if ($resultat_adh_nomail->fields[14]!="") 
					$coord_adh .= "<tr><td><B>".str_replace(" ","&nbsp;",_T("MSN:"))."</B>&nbsp;</td><td>".htmlentities($resultat_adh_nomail->fields[14], ENT_QUOTES)."</td></tr>";
				$coord_adh .= "</table>";
?>
								<td valign="top" bgcolor="<? echo $color ?>"<? echo $activity_class ?>"><? echo $coord_adh; ?></td> 
								<td valign="top" bgcolor="<? echo $color ?>"<? echo $activity_class ?>><? echo _T($resultat_adh_nomail->fields[5]) ?></td> 
								<td valign="top" bgcolor="<? echo $color ?>"<? echo $activity_class ?>><? echo $statut_cotis ?></td>
							</TR>

<?	
				$nomail_adh[]=$resultat_adh_nomail->fields[0];
				$resultat_adh_nomail->MoveNext();
			} 
			$resultat_adh_nomail->Close();
			

?>
						</TABLE>
						<BR>
						<DIV align="center">
						<TABLE>
							<TR>
<?
		if (!isset($_POST["mailing_confirmed"]))
		{
?>
								<TD>
									<FORM action="mailing_adherents.php" method="post">
<?
			reset($mailing_adh);
			while(list($key,$value)=each($mailing_adh))
			{
				echo "<INPUT type=\"hidden\" name=\"mailing_adh[]\" value=\"".$value."\">";
			}
?>
										<INPUT type ="hidden" name="mailing_corps" value="<? echo htmlentities($mailing_corps, ENT_QUOTES); ?>">
										<INPUT type ="hidden" name="mailing_objet" value="<? echo htmlentities($mailing_objet, ENT_QUOTES); ?>">
										<INPUT type="submit" value="<? echo _T("Go back"); ?>">&nbsp;&nbsp;&nbsp;
									</FORM>
								</TD>
<?
		}
		else
		{
?>
								<TD>
									<FORM action="gestion_adherents.php" method="post">
										<INPUT type="submit" value="<? echo _T("Go back"); ?>">
									</FORM>
								</TD>
<?
		}
?>								
								<TD>
									<FORM action="etiquettes_adherents.php" method="post" target="_blank">
<?
		reset($nomail_adh);
		while(list($key,$value)=each($nomail_adh))
		{
			echo "<INPUT type=\"hidden\" name=\"mailing_adh[]\" value=\"".$value."\">";
		}
?>
										&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="<? echo _T("Generate labels"); ?>">
									</FORM>
								</TD>
							<TR>
						</TABLE>
						</DIV>
<?
	}
	include("footer.php"); 
?>
