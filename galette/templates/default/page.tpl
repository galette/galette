<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"> 
<HTML> 
<HEAD> 
	<TITLE>Galette {$GALETTE_VERSION}</TITLE> 
	<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=iso-8859-1"> 
	<LINK rel="stylesheet" type="text/css" href="{$template_subdir}galette.css" > 
</HEAD> 
<BODY BGCOLOR="#FFFFFF">
	<DIV id="content">
{$content}	
		<DIV id="copyright">
			<A href="http://galette.logeek.com:8080/fr/" target="_blank">Galette {$GALETTE_VERSION}</A>
		</DIV>
	</DIV>
	<DIV id="menu">
		<DIV id="logo">
			<IMG src="{$template_subdir}images/galette.png" alt="[ Galette ]" width="129" height="60">
		</DIV>	
		<DIV id="nav1">
			<H1>{_T("Navigation")}</H1>
			<UL>
{if $smarty.session.admin_status eq 1}
				<LI><A href="gestion_adherents.php">{_T("List of members")}</A></LI>
				<LI><A href="gestion_contributions.php?id_adh=all">{_T("List of contributions")}</A></LI>
				<LI><A href="ajouter_adherent.php">{_T("Add a member")}</A></LI>
				<LI><A href="ajouter_contribution.php?cotis_extension=1">{_T("Add member fee")}</A></LI>
				<LI><A href="ajouter_contribution.php?cotis_extension=0">{_T("Add a contribution")}</A></LI>
				<LI><A href="mailing_adherents.php">{_T("Do a mailing")}</A></LI>
				<LI><A href="mailing_adherents.php?etiquettes=1">{_T("Generate labels")}</A></LI>
				<LI><A href="log.php">{_T("Logs")}</A></LI>
{else}
				<LI><A href="voir_adherent.php">{_T("My information")}</A></LI>
				<LI><A href="gestion_contributions.php">{_T("My contributions")}</A></LI>
{/if}				
			</UL>
		</DIV>
{if $smarty.session.admin_status eq 1}
		<DIV id="nav1">
			<H1>{_T("Configuration")}</H1>
			<UL>
				<LI><A href="preferences.php">{_T("Settings")}</A></LI>
				<LI><A href="configurer_fiches.php">{_T("Configure member forms")}</A></LI>
			</UL>
		</DIV>
{/if}
		<DIV id="logout">
			<A href="index.php?logout=1">{_T("Log off")}</A>
		</DIV>
{if $PAGENAME eq "gestion_adherents.php" || $PAGENAME eq "mailing_adherents.php"}
		<DIV id="legende">
			<H1>{_T("Legend")}</H1>
			<TABLE>
				<TR>
					<TD width="30" class="back"><IMG src="{$template_subdir}images/icon-male.png" Alt="{_T("[M]")}" align="middle" width="10" height="12"></TD>
					<TD class="back">{_T("Man")}</TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="{$template_subdir}images/icon-female.png" Alt="{_T("[W]")}" align="middle" width="9" height="12"></TD>
					<TD class="back">{_T("Woman")}</TD>
				</TR>
{if $PAGENAME eq "gestion_adherents.php"}
				<TR>
					<TD width="30" class="back"><IMG src="{$template_subdir}images/icon-mail.png" Alt="{_T("[Mail]")}" align="middle" border="0" width="14" height="10"></TD>
					<TD class="back">{_T("Send a mail")}</TD>
				</TR>
{/if}
				<TR>
					<TD width="30" class="back"><IMG src="{$template_subdir}images/icon-star.png" Alt="{_T("[admin]")}" align="middle" width="12" height="13"></TD>
					<TD class="back">{_T("Admin")}</TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="{$template_subdir}images/icon-edit.png" alt="{_T("[mod]")}" border="0" width="12" height="13"></TD>
					<TD class="back">{_T("Modification")}</TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="{$template_subdir}images/icon-money.png" alt="{_T("[$]")}" border="0" width="13" height="13"></TD>
					<TD class="back">{_T("Contributions")}</TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="{$template_subdir}images/icon-trash.png" alt="{_T("[del]")}" border="0" width="11" height="13"></TD>
					<TD class="back">{_T("Deletion")}</TD>
				</TR>
				<TR>
					<TD width="30" class="back">{_T("Name")}</TD>
					<TD class="back">{_T("Active account")}</TD>
				</TR>
				<TR>
					<TD width="30" class="inactif back">{_T("Name")}</TD>
					<TD class="back">{_T("Inactive account")}</TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-never color-sample">&nbsp;</TD>
					<TD class="back">{_T("Never contributed")}</TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-ok color-sample">&nbsp;</TD>
					<TD class="back">{_T("Membership in order")}</TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-soon color-sample">&nbsp;</TD>
					<TD class="back">{_T("Membership will expire soon (&lt;30d)")}</TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-late color-sample">&nbsp;</TD>
					<TD class="back">{_T("Lateness in fee")}</TD>
				</TR>
			</TABLE>
		</DIV>
{elseif $PAGENAME eq "gestion_contributions.php"}
		<DIV id="legende">
			<H1>{_T("Legend")}</H1>
			<TABLE>
{if $smarty.session.admin_status eq 1}
				<TR>
					<TD width="30" class="back"><IMG src="{$template_subdir}images/icon-edit.png" alt="{_T("[mod]")}" border="0" width="12" height="13"></TD>
					<TD class="back">{_T("Modification")}</TD>
				</TR>
				<TR>
					<TD width="30" class="back"><IMG src="{$template_subdir}images/icon-trash.png" alt="{_T("[del]")}" border="0" width="11" height="13"></TD>
					<TD class="back">{_T("Deletion")}</TD>
				</TR>
{/if}
				<TR>
					<TD width="30" class="cotis-normal color-sample">&nbsp;</TD>
					<TD class="back">{_T("Contribution")}</TD>
				</TR>
				<TR>
					<TD width="30" class="cotis-give color-sample">&nbsp;</TD>
					<TD class="back">{_T("Gift")}</TD>
				</TR>
			</TABLE>
		</DIV>
{/if}
	</DIV>
</BODY> 
</HTML>
