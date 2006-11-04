<?php
/* footer.php
 * - Pied de page
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
        include_once("includes/i18n.inc.php"); 

	$end = utime(); $run = $end - $start;
?>
		<DIV id="copyright">
			<A href="http://www.zopeuse.org/projets/galette/Wiki_galette/FrontPage">Galette <? echo GALETTE_VERSION ?></A> - <? echo _T("Page displayed in")." ".substr($run, 0, 5)." "._T("seconds."); ?>
		</DIV>
	</DIV>
	<DIV id="menu">
		<DIV id="logo">
			<IMG src="images/galette.png" alt="[ Galette ]" width="129" height="60">
		</DIV>	
		<DIV id="nav1">
			<H1><? echo _T("Navigation"); ?></H1>
			<UL>
<?
	if ($_SESSION["admin_status"]==1) 
	{
?>
				<LI><A href="gestion_adherents.php"><? echo _T("List of members"); ?></A></LI>
				<LI><A href="gestion_contributions.php?id_adh=all"><? echo _T("List of contributions"); ?></A></LI>
				<LI><A href="ajouter_adherent.php"><? echo _T("Add a member"); ?></A></LI>
				<LI><A href="ajouter_contribution.php"><? echo _T("Add a contribution"); ?></A></LI>
				<LI><A href="mailing_adherents.php"><? echo _T("Do a mailing"); ?></A></LI>
				<LI><A href="mailing_adherents.php?etiquettes=1"><? echo _T("Generate labels"); ?></A></LI>
				<LI><A href="log.php"><? echo _T("Logs"); ?></A></LI>
<?
	}
	else
	{
?>
				<LI><A href="voir_adherent.php"><? echo _T("My information"); ?></A></LI>
				<LI><A href="gestion_contributions.php"><? echo _T("My contributions"); ?></A></LI>
<?
	}
?>				
			</UL>
		</DIV>
<?
	if ($_SESSION["admin_status"]==1)
	{
?>
		<DIV id="nav1">
			<H1><? echo _T("Configuration"); ?></H1>
			<UL>
				<LI><A href="preferences.php"><? echo _T("Settings"); ?></A></LI>
				<LI><A href="configurer_fiches.php"><? echo _T("Configure member forms"); ?></A></LI>
			</UL>
		</DIV>
<?
	}
?>
		<DIV id="logout">
			<A href="index.php?logout=1"><? echo _T("Log off"); ?></A>
		</DIV>
<? 
	if (basename($_SERVER["SCRIPT_NAME"])=="gestion_adherents.php" || basename($_SERVER["SCRIPT_NAME"])=="mailing_adherents.php") 
	{
?>
		<DIV id="legende">
			<H1><? echo _T("Legend"); ?></H1>
			<TABLE>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-male.png" Alt="<? echo _T("[M]"); ?>" align="middle" width="10" height="12"></TD>
					<TD class="back"><? echo _T("Man"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-female.png" Alt="<? echo _T("[W]"); ?>" align="middle" width="9" height="12"></TD>
					<TD class="back"><? echo _T("Woman"); ?></TD>
				</TR>
<?
		if (basename($_SERVER["SCRIPT_NAME"])=="gestion_adherents.php")
		{
?>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-mail.png" Alt="<? echo _T("[Mail]"); ?>" align="middle" border="0" width="14" height="10"></TD>
					<TD class="back"><? echo _T("Send a mail"); ?></TD>
				</TR>
<?
		}
?>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-star.png" Alt="<? echo _T("[admin]"); ?>" align="middle" width="12" height="13"></TD>
					<TD class="back"><? echo _T("Admin"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-edit.png" alt="<? echo _T("[mod]"); ?>" border="0" width="12" height="13"></TD>
					<TD class="back"><? echo _T("Modification"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-money.png" alt="<? echo _T("[$]"); ?>" border="0" width="13" height="13"></TD>
					<TD class="back"><? echo _T("Contributions"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-trash.png" alt="<? echo _T("[del]"); ?>" border="0" width="11" height="13"></TD>
					<TD class="back"><? echo _T("Deletion"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="back"><? echo _T("Name"); ?></TD>
					<TD class="back"><? echo _T("Active account"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="inactif back"><? echo _T("Name"); ?></TD>
					<TD class="back"><? echo _T("Inactive account"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-never color-sample">&nbsp;</TD>
					<TD class="back"><? echo _T("Never contributed"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-ok color-sample">&nbsp;</TD>
					<TD class="back"><? echo _T("Membership in order"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-soon color-sample">&nbsp;</TD>
					<TD class="back"><? echo _T("Membership will expire soon (&lt;30d)"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-late color-sample">&nbsp;</TD>
					<TD class="back"><? echo _T("Lateness in fee"); ?></TD>
				</TR>
			</TABLE>
		</DIV>
<?
	}
	elseif (basename($_SERVER["SCRIPT_NAME"])=="gestion_contributions.php") 
	{
?>
		<DIV id="legende">
			<H1><? echo _T("Legend"); ?></H1>
			<TABLE>
<?
		if ($_SESSION["admin_status"]==1) 
		{
?>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-edit.png" alt="<? echo _T("[mod]"); ?>" border="0" width="12" height="13"></TD>
					<TD class="back"><? echo _T("Modification"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-trash.png" alt="<? echo _T("[del]"); ?>" border="0" width="11" height="13"></TD>
					<TD class="back"><? echo _T("Deletion"); ?></TD>
				</TR>
<?
		}
?>
				<TR>
					<TD width="30" class="cotis-normal color-sample">&nbsp;</TD>
					<TD class="back"><? echo _T("Contribution"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-give color-sample">&nbsp;</TD>
					<TD class="back"><? echo _T("Gift"); ?></TD>
				</TR>
			</TABLE>
		</DIV>
<?
	}
?>
	</DIV>
</BODY> 
</HTML>
