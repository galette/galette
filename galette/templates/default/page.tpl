{html_doctype xhtml=true type=strict omitxml=false encoding=UTF-8}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$galette_lang}" lang="{$galette_lang}">
	<head>
		{include file='common_header.tpl'}
{if $color_picker}
	<script type="text/javascript" src="{$jquery_dir}farbtastic.js"></script>
	<link rel="stylesheet" type="text/css" href="{$template_subdir}farbtastic.css"/>
{/if}
{if $html_editor}
	<script type="text/javascript" src="{$htmledi_dir}tiny_mce.js"></script>
	<script type="text/javascript">
		//<![CDATA[
		tinyMCE.init({ldelim}
			mode : "none",
			theme : "advanced",
			theme_advanced_toolbar_location : "top",
			theme_advanced_toolbar_align : "left",
			theme_advanced_statusbar_location : "bottom",
			theme_advanced_resizing : true,
			plugins : "safari,insertdatetime,preview,contextmenu,fullscreen,nonbreaking",
			theme_advanced_buttons1 : "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,outdent,indent,blockquote,|,styleselect,formatselect,fontselect,fontsizeselect",
			theme_advanced_buttons2 : "bullist,numlist,|,undo,redo,|,link,unlink,anchor,image,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,nonbreaking|,insertdate,inserttime,|,forecolor,backcolor,|,fullscreen,cleanup,help,code,preview",
			theme_advanced_buttons3 : "",
			language : "{$galette_lang}"
		{rdelim});

		function toggleEditor(id) {ldelim}
			var elm = document.getElementById(id);
		
			if (tinyMCE.getInstanceById(id) == null)
				tinyMCE.execCommand('mceAddControl', false, id);
			else
				tinyMCE.execCommand('mceRemoveControl', false, id);
		{rdelim}

		//]]>
	</script>
{/if}
{if $require_calendar}
	<link rel="stylesheet" type="text/css" href="{$template_subdir}datePicker.css"/>
	<script type="text/javascript" src="{$jquery_dir}jquery.datePicker.js"></script>
	<script type="text/javascript" src="{$jquery_dir}date.js"></script>
	{if $lang ne 'en'}
	<script type="text/javascript" src="{$jquery_dir}date_{$galette_lang}.js"></script>
	{/if}
	<!--<script type="text/javascript" src="{$scripts_dir}date_common.js"></script>-->
{/if}
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
			<h1>{_T string="Navigation"}</h1>
			<ul>
{if $smarty.session.admin_status eq 1}
				<li><a href="gestion_adherents.php">{_T string="List of members"}</a></li>
				<li><a href="gestion_contributions.php?id_adh=all">{_T string="List of contributions"}</a></li>
				<li><a href="gestion_transactions.php">{_T string="List of transactions"}</a></li>
				<li><a href="ajouter_adherent.php">{_T string="Add a member"}</a></li>
				<li><a href="ajouter_contribution.php">{_T string="Add a contribution"}</a></li>
				<li><a href="ajouter_transaction.php">{_T string="Add a transaction"}</a></li>
				<li><a href="history.php">{_T string="Logs"}</a></li>
{else}
				<li><a href="subscription_form.php?id_adh={$data.id_adh}">Fiche adherent</a></li>
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
				<li><a href="champs_requis.php">{_T string="Required fields"}</a></li>
				<li><a href="configurer_fiches.php">{_T string="Configure member forms"}</a></li>
				<li><a href="traduire_libelles.php">{_T string="Translate labels"}</a></li>
				<li><a href="gestion_textes.php">{_T string="Email contents"}</a></li>
				<li><a href="utilitaires.php">{_T string="Utilities"}</a></li>
			</ul>
		</div>
{/if}
		<div id="logout">
			<a href="index.php?logout=1">{_T string="Log off"}</a>
		</div>
		<ul id="langs">
{foreach item=langue from=$languages}
			<li><a href="?pref_lang={$langue->getID()}"><img src="{$langue->getFlag()}" alt="{$langue->getName()}" lang="{$langue->getAbbrev()}" class="flag"/></a></li>
{/foreach}
		</ul>

{if $PAGENAME eq "gestion_adherents.php" || $PAGENAME eq "mailing_adherents.php"}
		<div id="legende">
			<h1>{_T string="Legend"}</h1>
			<table>
				<tr>
					<th><img src="{$template_subdir}images/icon-male.png" alt="{_T string="Mister"}" width="16" height="16"/></th>
					<td class="back">{_T string="Man"}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-female.png" alt="{_T string="Miss"} / {_T string="Mrs"}" width="16" height="16"/></th>
					<td class="back">{_T string="Woman"}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-company.png" alt="{_T string="Society"}" width="16" height="16"/></th>
					<td class="back">{_T string="Society"}</td>
				</tr>
{if $PAGENAME eq "gestion_adherents.php"}
				<tr>
					<th><img src="{$template_subdir}images/icon-mail.png" alt="{_T string="E-mail"}" width="16" height="16"/></th>
					<td class="back">{_T string="Send a mail"}</td>
				</tr>
{/if}
				<tr>
					<th><img src="{$template_subdir}images/icon-star.png" alt="{_T string="Admin"}" width="16" height="16"/></th>
					<td class="back">{_T string="Admin"}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="Modify"}" width="16" height="16"/></th>
					<td class="back">{_T string="Modification"}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-money.png" alt="{_T string="Contribution"}" width="16" height="16"/></th>
					<td class="back">{_T string="Contributions"}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-trash.png" alt="{_T string="Delete"}" width="16" height="16"/></th>
					<td class="back">{_T string="Deletion"}</td>
				</tr>
				<tr>
					<th class="back">{_T string="Name"}</th>
					<td class="back">{_T string="Active account"}</td>
				</tr>
				<tr>
					<th class="inactif back">{_T string="Name"}</th>
					<td class="back">{_T string="Inactive account"}</td>
				</tr>
				<tr>
					<th class="cotis-never color-sample">&nbsp;</th>
					<td class="back">{_T string="Never contributed"}</td>
				</tr>
				<tr>
					<th class="cotis-ok color-sample">&nbsp;</th>
					<td class="back">{_T string="Membership in order"}</td>
				</tr>
				<tr>
					<th class="cotis-soon color-sample">&nbsp;</th>
					<td class="back">{_T string="Membership will expire soon (&lt;30d)"}</td>
				</tr>
				<tr>
					<th class="cotis-late color-sample">&nbsp;</th>
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
					<td class="back"><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16"/></td>
					<td class="back">{_T string="Modification"}</td>
				</tr>
				<tr>
					<td class="back"><img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16"/></td>
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
	<div id="content">
		<div class="content-box">
			{$content}
		</div>
		<div id="copyright">
			<a href="http://galette.tuxfamily.org/">Galette {$GALETTE_VERSION}</a>
		</div>
	</div>
{if $require_calendar}
	<script type="text/javascript">
		//<![CDATA[
			$.dpText = {ldelim}
				TEXT_PREV_YEAR		:	'{_T string="Previous year"}',
				TEXT_PREV_MONTH		:	'{_T string="Previous month"}',
				TEXT_NEXT_YEAR		:	'{_T string="Next year"}',
				TEXT_NEXT_MONTH		:	'{_T string="Next month"}',
				TEXT_CLOSE		:	'{_T string="Close"}',
				TEXT_CHOOSE_DATE	:	'{_T string="Choose date"}'
			{rdelim}
			$('.date-pick').datePicker().val(new Date().asString()).trigger('change');;
			$('.past-date-pick').datePicker({ldelim}startDate:'01/01/1900'{rdelim});
		//]]>
	</script>
{/if}
</body>
</html>
