{html_doctype xhtml=true type=strict omitxml=false encoding=iso-8859-1}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
<head>
	<title>Galette {$GALETTE_VERSION}</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"/>
	<link rel="stylesheet" type="text/css" href="{$template_subdir}galette.css"/>
</head>
<body>
	<div id="content">
<div class="content-box">
{$content}
</div>
		<div id="copyright">
			<a href="http://galette.tuxfamily.org/">Galette {$GALETTE_VERSION}</a>
		</div>
	</div>
	<div id="menu">
		<div id="logo">
  {if $smarty.session.customLogo}
  <img src="picture.php?id_adh=0" height="60" alt="[ Galette ]"/>
  {else}
  <img src="{$template_subdir}images/galette.png" alt="[ Galette ]" width="129" height="60"/>
  {/if}
		</div>
		<div class="nav1">
			<h1>{_T string="Navigation"}</h1>
			<ul>
{if $smarty.session.admin_status eq 1}
				<li><a href="gestion_adherents.php">{_T string="List of members"}</a></li>
				<li><a href="gestion_contributions.php?id_adh=all">{_T string="List of contributions"}</a></li>
				<li><a href="gestion_transactions.php">{_T string="List of transactions"}</a></li>
				<li><a href="ajouter_adherent.php">{_T string="Add a member"}</a></li>
				<li><a href="ajouter_contribution.php">{_T string="Add a contribution"}</a></li>
				<li><a href="ajouter_transaction.php">{_T string="Add a transaction"}</a></li>
				<li><a href="log.php">{_T string="Logs"}</a></li>
{else}
				<li><a href="voir_adherent.php">{_T string="My information"}</a></li>
				<li><a href="gestion_contributions.php">{_T string="My contributions"}</a></li>
				<li><a href="gestion_transactions.php">{_T string="My transactions"}</a></li>
{/if}
			</ul>
		</div>
{if $smarty.session.admin_status eq 1}
		<div class="nav1">
			<h1>{_T string="Configuration"}</h1>
			<ul>
				<li><a href="preferences.php">{_T string="Settings"}</a></li>
				<li><a href="configurer_fiches.php">{_T string="Configure member forms"}</a></li>
				<li><a href="traduire_libelles.php">{_T string="Translate labels"}</a></li>
			</ul>
		</div>
{/if}
		<div id="logout">
			<a href="index.php?logout=1">{_T string="Log off"}</a>
		</div>
{if $PAGENAME eq "gestion_adherents.php" || $PAGENAME eq "mailing_adherents.php"}
		<div id="legende">
			<h1>{_T string="Legend"}</h1>
			<table>
				<tr>
					<td class="back"><img src="{$template_subdir}images/icon-male.png" alt="{_T string="[M]"}" width="10" height="12"/></td>
					<td class="back">{_T string="Man"}</td>
				</tr>
				<tr>
					<td class="back"><img src="{$template_subdir}images/icon-female.png" alt="{_T string="[W]"}" width="9" height="12"/></td>
					<td class="back">{_T string="Woman"}</td>
				</tr>
{if $PAGENAME eq "gestion_adherents.php"}
				<tr>
					<td class="back"><img src="{$template_subdir}images/icon-mail.png" alt="{_T string="[Mail]"}" width="14" height="10"/></td>
					<td class="back">{_T string="Send a mail"}</td>
				</tr>
{/if}
				<tr>
					<td class="back"><img src="{$template_subdir}images/icon-star.png" alt="{_T string="[admin]"}" width="12" height="13"/></td>
					<td class="back">{_T string="Admin"}</td>
				</tr>
				<tr>
					<td class="back"><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="12" height="13"/></td>
					<td class="back">{_T string="Modification"}</td>
				</tr>
				<tr>
					<td class="back"><img src="{$template_subdir}images/icon-money.png" alt="{_T string="[$]"}" width="13" height="13"/></td>
					<td class="back">{_T string="Contributions"}</td>
				</tr>
				<tr>
					<td class="back"><img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="11" height="13"/></td>
					<td class="back">{_T string="Deletion"}</td>
				</tr>
				<tr>
					<td class="back">{_T string="Name"}</td>
					<td class="back">{_T string="Active account"}</td>
				</tr>
				<tr>
					<td class="inactif back">{_T string="Name"}</td>
					<td class="back">{_T string="Inactive account"}</td>
				</tr>
				<tr>
					<td class="cotis-never color-sample">&nbsp;</td>
					<td class="back">{_T string="Never contributed"}</td>
				</tr>
				<tr>
					<td class="cotis-ok color-sample">&nbsp;</td>
					<td class="back">{_T string="Membership in order"}</td>
				</tr>
				<tr>
					<td class="cotis-soon color-sample">&nbsp;</td>
					<td class="back">{_T string="Membership will expire soon (&lt;30d)"}</td>
				</tr>
				<tr>
					<td class="cotis-late color-sample">&nbsp;</td>
					<td class="back">{_T string="Lateness in fee"}</td>
				</tr>
			</table>
		</div>
{elseif $PAGENAME eq "gestion_contributions.php"}
		<div id="legende">
			<h1>{_T string="Legend"}</h1>
			<table>
{if $smarty.session.admin_status eq 1}
				<tr>
					<td class="back"><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="12" height="13"/></td>
					<td class="back">{_T string="Modification"}</td>
				</tr>
				<tr>
					<td class="back"><img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="11" height="13"/></td>
					<td class="back">{_T string="Deletion"}</td>
				</tr>
{/if}
				<tr>
					<td class="cotis-normal color-sample">&nbsp;</td>
					<td class="back">{_T string="Contribution"}</td>
				</tr>
				<tr>
					<td class="cotis-give color-sample">&nbsp;</td>
					<td class="back">{_T string="Gift"}</td>
				</tr>
			</table>
		</div>
{/if}
	</div>
</body>
</html>
