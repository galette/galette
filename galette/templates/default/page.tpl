{html_doctype xhtml=true type=strict omitxml=false encoding=UTF-8}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$galette_lang}" lang="{$galette_lang}">
	<head>
		{include file='common_header.tpl'}
{if $color_picker}
		<script type="text/javascript" src="{$jquery_dir}farbtastic.js"></script>
		<link rel="stylesheet" type="text/css" href="{$template_subdir}farbtastic.css"/>
{/if}
{* JQuery UI related *}
		{* UI accordion is used for main menu ; we have to require it and UI core *}
		<script type="text/javascript" src="{$jquery_dir}ui-{$jquery_ui_version}/ui.core.min.js"></script>
		<script type="text/javascript" src="{$jquery_dir}ui-{$jquery_ui_version}/ui.accordion.min.js"></script>
{if $require_sorter}
		<script type="text/javascript" src="{$jquery_dir}ui-{$jquery_ui_version}/ui.sortable.min.js"></script>

{/if}
{if $require_calendar}
		<script type="text/javascript" src="{$jquery_dir}ui-{$jquery_ui_version}/ui.datepicker.min.js"></script>
	{if $lang ne 'en'}
		<script type="text/javascript" src="{$jquery_dir}ui-{$jquery_ui_version}/i18n/ui.datepicker-{$galette_lang}.min.js"></script>
	{/if}
		<link rel="stylesheet" type="text/css" href="{$template_subdir}jquery-ui/jquery-ui-{$jquery_ui_version}.custom.css" />
{/if}
{if $require_tabs}
		<script type="text/javascript" src="{$jquery_dir}ui-{$jquery_ui_version}/ui.tabs.min.js"></script>
{/if}
{if $require_dialog}
		<script type="text/javascript" src="{$jquery_dir}ui-{$jquery_ui_version}/ui.dialog.min.js"></script>
{/if}
		{* UI accordion is used for main menu ; we need the CSS *}
		<link rel="stylesheet" type="text/css" href="{$template_subdir}jquery-ui/jquery-ui-{$jquery_ui_version}.custom.css" />
{* /JQuery UI related *}
{if $html_editor}
	{if !$plugged_html_editor}
		<script type="text/javascript" src="{$jquery_dir}markitup-{$jquery_markitup_version}/jquery.markitup.pack.js"/>
		<script type="text/javascript" src="{$jquery_dir}markitup-{$jquery_markitup_version}/sets/html/set-{$galette_lang}.js"></script>
		<link rel="stylesheet" type="text/css" href="{$jquery_dir}markitup-{$jquery_markitup_version}/skins/galette/style.css" />
		<link rel="stylesheet" type="text/css" href="{$jquery_dir}markitup-{$jquery_markitup_version}/sets/html/style.css" />
		<script language="javascript">
			//<![CDATA[
			function toggleMailingEditor(id) {ldelim}
				if(!$('#mailing_html').attr('checked')){ldelim}
					$('#mailing_html').attr('checked', true);
				{rdelim}

				$('input#html_editor_active').attr('value', '1');
				{* While it is not possible to deactivate markItUp, we remove completly the functionnality *}
				$('#toggle_editor').remove();
				$('#mailing_corps').markItUp(galetteSettings);
			{rdelim}
		{if $html_editor_active eq 1}
			$(document).ready(function(){ldelim}
				{* While it is not possible to deactivate markItUp, we remove completly the functionnality *}
				$('#toggle_editor').remove();
				$('#mailing_corps').markItUp(galetteSettings);
			{rdelim});
		{/if}
			//]]>
		</script>
	{/if}
{/if}
{* If some additionnals headers should be added from plugins, we load the relevant template file
We have to use a template file, so Smrty will do its work (like replacing variables). *}
{if $headers|@count != 0}
	{foreach from=$headers item=header}
		{include file=$header}
	{/foreach}
{/if}
</head>
<body>
	<div id="menu">
		<div id="logo">
			<img src="{$galette_base_path}picture.php?logo=true" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" />
		</div>
		<h1 class="nojs">{_T string="Navigation"}</h1>
		<ul>
{if $login->isAdmin()}
			<li{if $PAGENAME eq "gestion_adherents.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_adherents.php" title="{_T string="View, search into and filter member's list"}">{_T string="List of members"}</a></li>
			<li{if $PAGENAME eq "gestion_contributions.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_contributions.php?id_adh=all" title="{_T string="View and filter contributions"}">{_T string="List of contributions"}</a></li>
			<li{if $PAGENAME eq "gestion_transactions.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_transactions.php" title="{_T string="View and filter transactions"}">{_T string="List of transactions"}</a></li>
			<li{if $PAGENAME eq "ajouter_adherent.php"} class="selected"{/if}><a href="{$galette_base_path}ajouter_adherent.php" title="{_T string="Add new member in database"}">{_T string="Add a member"}</a></li>
			<li{if $PAGENAME eq "ajouter_contribution.php"} class="selected"{/if}><a href="{$galette_base_path}ajouter_contribution.php" title="{_T string="Add new contribution in database"}">{_T string="Add a contribution"}</a></li>
			<li{if $PAGENAME eq "ajouter_transaction.php"} class="selected"{/if}><a href="{$galette_base_path}ajouter_transaction.php" title="{_T string="Add new transaction in database"}">{_T string="Add a transaction"}</a></li>
			<li{if $PAGENAME eq "history.php"} class="selected"{/if}><a href="{$galette_base_path}history.php" title="{_T string="View application's logs"}">{_T string="Logs"}</a></li>
			<li class="mnu_last{if $PAGENAME eq "ajouter_adherent.php"} selected{/if}"><a href="{$galette_base_path}export.php" title="{_T string="Export some datas in various formats"}">{_T string="Exports"}</a></li>
{else}
			<li {if $PAGENAME eq "voir_adherent.php"} class="selected"{/if}><a href="{$galette_base_path}voir_adherent.php" title="{_T string="View my member card"}">{_T string="My information"}</a></li>
			<li{if $PAGENAME eq "gestion_contributions.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_contributions.php" title="{_T string="View and filter all my contributions"}">{_T string="My contributions"}</a></li>
			<li{if $PAGENAME eq "gestion_transactions.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_transactions.php" title="{_T string="View and filter all my transactions"}">{_T string="My transactions"}</a></li>
			<li class="mnu_last"><a href="{$galette_base_path}subscription_form.php?id_adh={$data.id_adh}" title="{_T string="My member card in PDF format"}">{_T string="PDF card"}</a></li>
{/if}
		</ul>
{if $login->isAdmin()}
		<h1 class="nojs">{_T string="Configuration"}</h1>
		<ul>
			<li{if $PAGENAME eq "preferences.php"} class="selected"{/if}><a href="{$galette_base_path}preferences.php" title="{_T string="Set applications preferences (adress, website, member's cards configuration, ...)"}">{_T string="Settings"}</a></li>
			<li{if $PAGENAME eq "config_fields.php"} class="selected"{/if}><a href="{$galette_base_path}config_fields.php" title="{_T string="Customize fields order, set which are required, and for who they're visibles"}">{_T string="Customize fields"}</a></li>
			<li{if $PAGENAME eq "champs_requis.php"} class="selected"{/if}><a href="{$galette_base_path}champs_requis.php">{_T string="Required fields"}</a></li>
			<li{if $PAGENAME eq "configurer_fiches.php"} class="selected"{/if}><a href="{$galette_base_path}configurer_fiches.php" title="{_T string="Manage additional fields for various forms"}">{_T string="Configure member forms"}</a></li>
			<li{if $PAGENAME eq "traduire_libelles.php"} class="selected"{/if}><a href="{$galette_base_path}traduire_libelles.php" title="{_T string="Translate additionnals fields labels"}">{_T string="Translate labels"}</a></li>
			<li{if $PAGENAME eq "gestion_intitules.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_intitules.php" title="{_T string="Manage various lists that are used in the application"}">{_T string="Manage lists"}</a></li>
			<li{if $PAGENAME eq "gestion_textes.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_textes.php" title="{_T string="Manage emails texts and subjects"}">{_T string="Emails content"}</a></li>
			<li class="mnu_last{if $PAGENAME eq "utilitaires.php"} selected{/if}"><a href="{$galette_base_path}utilitaires.php">{_T string="Utilities"}</a></li>
		</ul>
{/if}

{* Include plugins menu entries *}
{$plugins->getMenus()}

		<div id="logout">
			<a href="{$galette_base_path}index.php?logout=1">{_T string="Log off"}</a>
		</div>
		<ul id="langs">
{foreach item=langue from=$languages}
			<li><a href="?pref_lang={$langue->getID()}"><img src="{$langue->getFlag()}" alt="{$langue->getName()}" lang="{$langue->getAbbrev()}" class="flag"/></a></li>
{/foreach}
		</ul>
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
