{html_doctype xhtml=true type=strict omitxml=false encoding=iso-8859-1}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$galette_lang}" lang="{$galette_lang}">
<head>
	<title>{if $pref_slogan ne ""}{$pref_slogan} - {/if}{if $page_title ne ""}{$page_title} - {/if}Galette {$GALETTE_VERSION}</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"/>
	<meta http-equiv="Content-Style-Type" content="text/css" />
	<link rel="stylesheet" type="text/css" href="{$template_subdir}galette.css"/>
	<script type="text/javascript" src="{$jquery_dir}jquery-1.2.1.pack.js"></script>
	<script type="text/javascript" src="{$jquery_dir}jquery.bgiframe.pack.js"></script>
	<script type="text/javascript" src="{$jquery_dir}jquery.dimensions.pack.js"></script>
	<script type="text/javascript" src="{$jquery_dir}jquery.bgFade.js"></script>
	<script type="text/javascript" src="{$jquery_dir}jquery.corner.js"></script>
	<script type="text/javascript" src="{$jquery_dir}chili-1.7.pack.js"></script>
	<script type="text/javascript" src="{$jquery_dir}jquery.tooltip.pack.js"></script>
	<script type="text/javascript" src="{$scripts_dir}common.js"></script>
</head>
<body>
	<div id="menu">
		<div id="logo">
  {if $smarty.session.customLogo}
  <img src="photos/0.{$smarty.session.customLogoFormat}" height="60" alt="[ Galette ]"/>
  {else}
  <img src="{$template_subdir}images/galette.png" alt="[ Galette ]" width="129" height="60"/>  
  {/if}
		</div>
		<div class="nav1">
			<h1>{_T("Navigation")}</h1>
			<ul>
{if $smarty.session.admin_status eq 1}
				<li><a href="gestion_adherents.php">{_T("List of members")}</a></li>
				<li><a href="gestion_contributions.php?id_adh=all">{_T("List of contributions")}</a></li>
				<li><a href="gestion_transactions.php">{_T("List of transactions")}</a></li>
				<li><a href="ajouter_adherent.php">{_T("Add a member")}</a></li>
				<li><a href="ajouter_contribution.php">{_T("Add a contribution")}</a></li>
				<li><a href="ajouter_transaction.php">{_T("Add a transaction")}</a></li>
				<li><a href="log.php">{_T("Logs")}</a></li>
{else}
				<li><a href="voir_adherent.php">{_T("My information")}</a></li>
				<li><a href="gestion_contributions.php">{_T("My contributions")}</a></li>
				<li><a href="gestion_transactions.php">{_T("My transactions")}</a></li>
{/if}
			</ul>
		</div>
{if $smarty.session.admin_status eq 1}
		<div class="nav1">
			<h1>{_T("Configuration")}</h1>
			<ul>
				<li><a href="preferences.php">{_T("Settings")}</a></li>
				<li><a href="champs_requis.php">{_T("Required fields")}</a></li>
				<li><a href="configurer_fiches.php">{_T("Configure member forms")}</a></li>
				<li><a href="traduire_libelles.php">{_T("Translate labels")}</a></li>
				<li><a href="gestion_textes.php">{_T("Email contents")}</a></li>
			</ul>
		</div>
{/if}
		<div id="logout">
			<a href="index.php?logout=1">{_T("Log off")}</a>
		</div>
{if $PAGENAME eq "gestion_adherents.php" || $PAGENAME eq "mailing_adherents.php"}
		<div id="legende">
			<h1>{_T("Legend")}</h1>
			<table>
				<tr>
					<th><img src="{$template_subdir}images/icon-male.png" alt="{_T("Mister")}" width="16" height="16"/></th>
					<td class="back">{_T("Man")}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-female.png" alt="{_T("Miss")} / {_T("Mrs")}" width="16" height="16"/></th>
					<td class="back">{_T("Woman")}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-company.png" alt="{_T("Society")}" width="16" height="16"/></th>
					<td class="back">{_T("Society")}</td>
				</tr>
{if $PAGENAME eq "gestion_adherents.php"}
				<tr>
					<th><img src="{$template_subdir}images/icon-mail.png" alt="{_T("E-mail")}" width="16" height="16"/></th>
					<td class="back">{_T("Send a mail")}</td>
				</tr>
{/if}
				<tr>
					<th><img src="{$template_subdir}images/icon-star.png" alt="{_T("Admin")}" width="16" height="16"/></th>
					<td class="back">{_T("Admin")}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-edit.png" alt="{_T("Modify")}" width="16" height="16"/></th>
					<td class="back">{_T("Modification")}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-money.png" alt="{_T("Contribution")}" width="16" height="16"/></th>
					<td class="back">{_T("Contributions")}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-trash.png" alt="{_T("Delete")}" width="16" height="16"/></th>
					<td class="back">{_T("Deletion")}</td>
				</tr>
				<tr>
					<th class="back">{_T("Name")}</th>
					<td class="back">{_T("Active account")}</td>
				</tr>
				<tr>
					<th class="inactif back">{_T("Name")}</th>
					<td class="back">{_T("Inactive account")}</td>
				</tr>
				<tr>
					<th class="cotis-never color-sample">&nbsp;</th>
					<td class="back">{_T("Never contributed")}</td>
				</tr>
				<tr>
					<th class="cotis-ok color-sample">&nbsp;</th>
					<td class="back">{_T("Membership in order")}</td>
				</tr>
				<tr>
					<th class="cotis-soon color-sample">&nbsp;</th>
					<td class="back">{_T("Membership will expire soon (&lt;30d)")}</td>
				</tr>
				<tr>
					<th class="cotis-late color-sample">&nbsp;</th>
					<td class="back">{_T("Lateness in fee")}</td>
				</tr>
			</table>
		</div>
{elseif $PAGENAME eq "gestion_contributions.php"}
		<div id="legende">
			<h1>{_T("Legend")}</h1>
			<table>
{if $smarty.session.admin_status eq 1}
				<tr>
					<td class="back"><img src="{$template_subdir}images/icon-edit.png" alt="{_T("[mod]")}" width="16" height="16"/></td>
					<td class="back">{_T("Modification")}</td>
				</tr>
				<tr>
					<td class="back"><img src="{$template_subdir}images/icon-trash.png" alt="{_T("[del]")}" width="16" height="16"/></td>
					<td class="back">{_T("Deletion")}</td>
				</tr>
{/if}
				<tr>
					<td class="cotis-normal color-sample">&nbsp;</td>
					<td class="back">{_T("Contribution")}</td>
				</tr>
				<tr>
					<td class="cotis-give color-sample">&nbsp;</td>
					<td class="back">{_T("Gift")}</td>
				</tr>
			</table>
		</div>
{/if}
	</div>
	<div id="content">
		<div class="content-box">
			{$content}
		</div>
		<div id="copyright">
			<a href="http://galette.tuxfamily.org/">Galette {$GALETTE_VERSION}</a>
		</div>
	</div>
</body>
</html>
