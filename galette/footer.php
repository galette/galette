<?
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
			<A href="http://www.zopeuse.org/projets/galette/Wiki_galette/FrontPage">Galette <? echo GALETTE_VERSION ?></A> - <? echo _("Page affichée en")." ".substr($run, 0, 5)." "._("secondes."); ?>
		</DIV>
	</DIV>
	<DIV id="menu">
		<DIV id="logo">
			<IMG src="images/galette.jpg" alt="[ Galette ]" width="103" height="80"><BR>
			Galette
		</DIV>	
		<DIV id="nav1">
			<H1><? echo _("Navigation"); ?></H1>
			<UL>
<?
	if ($_SESSION["admin_status"]==1) 
	{
?>
				<LI><A href="gestion_adherents.php"><? echo _("Liste des adhérents"); ?></A></LI>
				<LI><A href="gestion_contributions.php"><? echo _("Liste des contributions"); ?></A></LI>
				<LI><A href="ajouter_adherent.php"><? echo _("Ajouter un adhérent"); ?></A></LI>
				<LI><A href="ajouter_contribution.php"><? echo _("Ajouter une contribution"); ?></A></LI>
				<LI><A href="mailing_adherents.php"><? echo _("Effectuer un mailing"); ?></A></LI>
				<LI><A href="mailing_adherents.php?etiquettes=1"><? echo _("Génération d'étiquettes"); ?></A></LI>
				<LI><A href="log.php"><? echo _("Historique"); ?></A></LI>
<?
	}
	else
	{
?>
				<LI><A href="voir_adherent.php"><? echo _("Mes informations"); ?></A></LI>
				<LI><A href="gestion_contributions.php"><? echo _("Mes contributions"); ?></A></LI>
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
			<H1><? echo _("Configuration"); ?></H1>
			<UL>
				<LI><A href="preferences.php"><? echo _("Préférences"); ?></A></LI>
				<LI><A href="configurer_fiches.php"><? echo _("Configurer les fiches"); ?></A></LI>
			</UL>
		</DIV>
<?
	}
?>
		<DIV id="logout">
			<A href="index.php?logout=1"><? echo _("Déconnexion"); ?></A>
		</DIV>
<? 
	if (basename($_SERVER["SCRIPT_NAME"])=="gestion_adherents.php" || basename($_SERVER["SCRIPT_NAME"])=="mailing_adherents.php") 
	{
?>
		<DIV id="legende">
			<H1><? echo _("Légende"); ?></H1>
			<TABLE>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-male.png" Alt="<? echo _("[H]"); ?>" align="middle" width="10" height="12"></TD>
					<TD class="back"><? echo _("Homme"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-female.png" Alt="<? echo _("[F]"); ?>" align="middle" width="9" height="12"></TD>
					<TD class="back"><? echo _("Femme"); ?></TD>
				</TR>
<?
		if (basename($_SERVER["SCRIPT_NAME"])=="gestion_adherents.php")
		{
?>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-mail.png" Alt="<? echo _("[Mail]"); ?>" align="middle" border="0" width="14" height="10"></TD>
					<TD class="back"><? echo _("Envoyer un mail"); ?></TD>
				</TR>
<?
		}
?>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-star.png" Alt="<? echo _("[admin]"); ?>" align="middle" width="12" height="13"></TD>
					<TD class="back"><? echo _("Administrateur"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-edit.png" alt="<? echo _("[mod]"); ?>" border="0" width="12" height="13"></TD>
					<TD class="back"><? echo _("Modification"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-money.png" alt="<? echo _("[$]"); ?>" border="0" width="13" height="13"></TD>
					<TD class="back"><? echo _("Contributions"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-trash.png" alt="<? echo _("[sup]"); ?>" border="0" width="11" height="13"></TD>
					<TD class="back"><? echo _("Suppression"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="back"><? echo _("Nom"); ?></TD>
					<TD class="back"><? echo _("Compte actif"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="inactif back"><? echo _("Nom"); ?></TD>
					<TD class="back"><? echo _("Compte désactivé"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-never color-sample">&nbsp;</TD>
					<TD class="back"><? echo _("N'a jamais cotisé"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-ok color-sample">&nbsp;</TD>
					<TD class="back"><? echo _("Adhésion en règle"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-soon color-sample">&nbsp;</TD>
					<TD class="back"><? echo _("Adhésion à échéance (<30j)"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-late color-sample">&nbsp;</TD>
					<TD class="back"><? echo _("Retard de cotisation"); ?></TD>
				</TR>
			</TABLE>
		</DIV>
<?
	}
	elseif (basename($_SERVER["SCRIPT_NAME"])=="gestion_contributions.php") 
	{
?>
		<DIV id="legende">
			<H1><? echo _("Légende"); ?></H1>
			<TABLE>
<?
		if ($_SESSION["admin_status"]==1) 
		{
?>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-edit.png" alt="<? echo _("[mod]"); ?>" border="0" width="12" height="13"></TD>
					<TD class="back"><? echo _("Modification"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="images/icon-trash.png" alt="<? echo _("[sup]"); ?>" border="0" width="11" height="13"></TD>
					<TD class="back"><? echo _("Suppression"); ?></TD>
				</TR>
<?
		}
?>
				<TR>
					<TD width="30" class="cotis-normal color-sample">&nbsp;</TD>
					<TD class="back"><? echo _("Cotisation"); ?></TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-give color-sample">&nbsp;</TD>
					<TD class="back"><? echo _("Don"); ?></TD>
				</TR>
			</TABLE>
		</DIV>
<?
	}
?>
	</DIV>
</BODY> 
</HTML>
