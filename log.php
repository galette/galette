<? 
/* log.php
 * - Historique
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
		
	if (isset($_POST["reset"]))
	{
		$requete[0] = "DELETE FROM ".PREFIX_DB."logs";
		$DB->Execute($requete[0]);
		dblog(_("Réinitialisation de l'historique"));
	}

    // Tri
	if (isset($_GET["tri"]))
		if (is_numeric($_GET["tri"]))
		{
			if ($_SESSION["tri_log"]==$_GET["tri"])
				$_SESSION["tri_log_sens"]=($_SESSION["tri_log_sens"]+1)%2;
			else
			{
				$_SESSION["tri_log"]=$_GET["tri"];
				$_SESSION["tri_log_sens"]=0;
			}
		}
    
	$requete[0] = "SELECT date_log, adh_log, text_log, ip_log FROM ".PREFIX_DB."logs ";
	$requete[1] = "SELECT count(id_log) FROM ".PREFIX_DB."logs";
	
    // phase de tri	
	if ($_SESSION["tri_log_sens"]=="0")
		$tri_log_sens_txt="ASC";
	else
		$tri_log_sens_txt="DESC";

	$requete[0] .= "ORDER BY ";
	
	// tri par date
	if ($_SESSION["tri_log"]=="0")
		$requete[0] .= "date_log ".$tri_log_sens_txt.",";
		
	// tri par ip
	elseif ($_SESSION["tri_log"]=="1")
		$requete[0] .= "ip_log ".$tri_log_sens_txt.",";

	// tri par adhérent
	elseif ($_SESSION["tri_log"]=="2")
		$requete[0] .= "adh_log ".$tri_log_sens_txt.",";

	// tri par description
	elseif ($_SESSION["tri_log"]=="3")
		$requete[0] .= "text_log ".$tri_log_sens_txt.",";
    
    $requete[0] .= "id_log ".$tri_log_sens_txt;
    
    $resultat = &$DB->SelectLimit($requete[0],PREF_NUMROWS,($page-1)*PREF_NUMROWS);
	$nb_lines = &$DB->Execute($requete[1]);
		
	include("header.php");

	if ($nb_lines->fields[0]%PREF_NUMROWS==0) 
		$nbpages = intval($nb_lines->fields[0]/PREF_NUMROWS);
	else 
		$nbpages = intval($nb_lines->fields[0]/PREF_NUMROWS)+1;
	$pagestring = "";
        if ($nbpages==0)
		$pagestring = "<b>1</b>";
	else for ($i=1;$i<=$nbpages;$i++)
	{
		if ($i!=$page)
			$pagestring .= "<A href=\"log.php?page=".$i."\">".$i."</A> ";
		else
			$pagestring .= $i." ";
	}
	
?>
	<H1 class="titre"><? echo _("Historique"); ?></H1>
	<FORM action="log.php" method="post">
		<DIV align="center"><INPUT type="submit" value="<? echo _("Réinitialisation de l'historique") ?>"></DIV>
		<INPUT type="hidden" name="reset" value="1">
	</FORM>
	<TABLE id="infoline" width="100%">
		<TR>
			<TD class="left"><? echo $nb_lines->fields[0]." "; if ($nb_lines->fields[0]!=1) echo _("lignes"); else echo _("ligne"); ?></TD>
			<TD class="right"><? echo _("Pages :"); ?> <SPAN class="pagelink"><? echo $pagestring; ?></SPAN></TD>
		</TR>
	</TABLE>
		<TABLE width="100%"> 
		<TR> 
			<TH width="15" class="listing">#</TH> 
  			<TH class="listing left" width="150">
				<A href="log.php?tri=0" class="listing"><? echo _("Date"); ?></A>
<?
	if ($_SESSION["tri_log"]=="0")
	{
		if ($_SESSION["tri_log_sens"]=="0")
			$img_sens = "asc.png";
		else
			$img_sens = "desc.png";
	}
	else
		$img_sens = "icon-empty.png";
?>
				<IMG src="images/<? echo $img_sens; ?>" width="7" height="7" alt="">
            </TH> 
  			<TH class="listing left" width="150">
				<A href="log.php?tri=1" class="listing"><? echo _("IP"); ?></A>
<?
	if ($_SESSION["tri_log"]=="1")
	{
		if ($_SESSION["tri_log_sens"]=="0")
			$img_sens = "asc.png";
		else
			$img_sens = "desc.png";
	}
	else
		$img_sens = "icon-empty.png";
?>
				<IMG src="images/<? echo $img_sens; ?>" width="7" height="7" alt="">
            </TH> 
  			<TH class="listing left" width="150">
				<A href="log.php?tri=2" class="listing"><? echo _("Adhérent"); ?></A>
<?
	if ($_SESSION["tri_log"]=="2")
	{
		if ($_SESSION["tri_log_sens"]=="0")
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
				<A href="log.php?tri=3" class="listing"><? echo _("Description"); ?></A>
<?
	if ($_SESSION["tri_log"]=="3")
	{
		if ($_SESSION["tri_log_sens"]=="0")
			$img_sens = "asc.png";
		else
			$img_sens = "desc.png";
	}
	else
		$img_sens = "icon-empty.png";
?>
				<IMG src="images/<? echo $img_sens; ?>" width="7" height="7" alt="">
            </TH>
  		</TR>
<? 
	$compteur = 1+($page-1)*PREF_NUMROWS;
	if ($resultat->EOF)
	{
?>	
		<TR><TD colspan="5" class="emptylist"><? echo _("historique vide"); ?></TD></TR>
<?
	}
	else while (!$resultat->EOF) 
	{ 
?>
		<TR class="cotis-never">
			<TD width="15" valign="top"><? echo $compteur ?></TD> 
			<TD valign="top" nowrap><? echo $resultat->fields[0]; ?></TD>
			<TD valign="top" nowrap><? echo $resultat->fields[3]; ?></TD>
			<TD valign="top" nowrap><? echo $resultat->fields[1]; ?></TD>
			<TD valign="top"><? echo nl2br(htmlentities(stripslashes(addslashes($resultat->fields[2])), ENT_QUOTES)); ?></TD>
		</TR>
<?
		$resultat->MoveNext();
		$compteur++;
	}
	$resultat->Close();
?>
  	</TABLE>	
	<DIV id="infoline2" class="right"><? echo _("Pages :"); ?> <SPAN class="pagelink"><? echo $pagestring; ?></SPAN></DIV>
<?
	include("footer.php");
?>
