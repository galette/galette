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
		$requete[0] = "DELETE FROM logs";
		$DB->Execute($requete[0]);
		dblog(_T("Réinitialisation de l'historique"));
	}

	$requete[0] = "SELECT date_log, adh_log, text_log, ip_log FROM logs ORDER BY date_log DESC, id_log DESC";
	$requete[1] = "SELECT count(id_log) FROM logs";
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
	<H1 class="titre"><? echo _T("Historique"); ?></H1>
	<FORM action="log.php" method="post">
		<DIV align="center"><INPUT type="submit" value="<? echo _T("Réinitialisation de l'historique") ?>"></DIV>
		<INPUT type="hidden" name="reset" value="1">
	</FORM>
	<TABLE id="infoline" width="100%">
		<TR>
			<TD class="left"><? echo $nb_lines->fields[0]." "; if ($nb_lines->fields[0]!=1) echo _T("lignes"); else echo _T("ligne"); ?></TD>
			<TD class="right"><? echo _T("Pages :"); ?> <SPAN class="pagelink"><? echo $pagestring; ?></SPAN></TD>
		</TR>
	</TABLE>
		<TABLE width="100%"> 
		<TR> 
			<TH width="15" class="listing">#</TH> 
  			<TH class="listing left" width="150"><? echo _T("Date"); ?></TH> 
  			<TH class="listing left" width="150"><? echo _T("IP"); ?></TH> 
  			<TH class="listing left" width="150"><? echo _T("Adhérent"); ?></TH> 
  			<TH class="listing left"><? echo _T("Description"); ?></TH>
  		</TR>
<? 
	$compteur = 1+($page-1)*PREF_NUMROWS;
	if ($resultat->EOF)
	{
?>	
		<TR><TD colspan="5" class="emptylist"><? echo _T("historique vide"); ?></TD></TR>
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
	<DIV id="infoline2" class="right"><? echo _T("Pages :"); ?> <SPAN class="pagelink"><? echo $pagestring; ?></SPAN></DIV>
<?
	include("footer.php");
?>