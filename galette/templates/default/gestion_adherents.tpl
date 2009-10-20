		<form action="gestion_adherents.php" method="get" id="filtre">
		<h1 id="titre" class="ui-corner-all">{_T string="Management of members"}</h1>
{if $error_detected|@count != 0}
		<div id="errorbox">
			<h1>{_T string="- ERROR -"}</h1>
			<ul>
{foreach from=$error_detected item=error}
				<li>{$error}</li>
{/foreach}
			</ul>
		</div>
{/if}
		<div id="listfilter">
			<label for="filtre_nom">{_T string="Search:"}&nbsp;</label>
			<input type="text" name="filtre_nom" id="filtre_nom" value="{$smarty.session.filtre_adh_nom}"/>&nbsp;
		 	{_T string="in:"}&nbsp;
			<select name="filtre_fld">
				{html_options options=$filtre_fld_options selected=$smarty.session.filtre_adh_fld}
			</select>
		 	{_T string="among:"}&nbsp;
			<select name="filtre" onchange="form.submit()">
				{html_options options=$filtre_options selected=$smarty.session.filtre_adh}
			</select>
			<select name="filtre_2" onchange="form.submit()">
				{html_options options=$filtre_2_options selected=$smarty.session.filtre_adh_2}
			</select>
			<input type="submit" class="submit inline" value="{_T string="Filter"}"/>
		</div>
		<table class="infoline">
			<tr>
				<td class="left">{$nb_members} {if $nb_members != 1}{_T string="members"}{else}{_T string="member"}{/if}</td>
				<td class="center">
					<label for="nbshow">{_T string="Show:"}</label>
					<select name="nbshow" id="nbshow">
						{html_options options=$nbshow_options selected=$numrows}
					</select>
					<noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
				</td>
				<td class="right">{_T string="Pages:"}
					<span class="pagelink">
					{section name="pageLoop" start=1 loop=$nb_pages+1}
						{if $smarty.section.pageLoop.index eq $page}
							{$smarty.section.pageLoop.index}
						{else}
							<a href="gestion_adherents.php?nbshow={$smarty.get.nbshow}&amp;page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
						{/if}
					{/section}
					</span>
				</td>
			</tr>
		</table>
		</form>
		<form action="gestion_adherents.php" method="post" id="listform">
		<table id="listing">
			<thead>
				<tr> 
					<th class="listing" id="id_row">#</th>
					<th class="listing left"> 
						<a href="gestion_adherents.php?tri=0" class="listing">
							{_T string="Name"}
							{if $smarty.session.tri_adh eq 0}
							{if $smarty.session.tri_adh_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>
					</th>
					<th class="listing left">
						<a href="gestion_adherents.php?tri=1" class="listing">
							{_T string="Nickname"}
							{if $smarty.session.tri_adh eq 1}
							{if $smarty.session.tri_adh_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>
					</th> 
					<th class="listing left"> 
						<a href="gestion_adherents.php?tri=2" class="listing">
							{_T string="Status"}
							{if $smarty.session.tri_adh eq 2}
							{if $smarty.session.tri_adh_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>
					</th> 
					<th class="listing left"> 
						<a href="gestion_adherents.php?tri=3" class="listing">
							{_T string="State of dues"}
							{if $smarty.session.tri_adh eq 3}
							{if $smarty.session.tri_adh_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>
					</th> 
					<th class="listing">{_T string="Actions"}</th> 
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="6" class="right" id="table_footer">
						{_T string="Pages:"}
						<span class="pagelink">
						{section name="pageLoop" start=1 loop=$nb_pages+1}
							{if $smarty.section.pageLoop.index eq $page}
								{$smarty.section.pageLoop.index}
							{else}
								<a href="gestion_adherents.php?nbshow={$smarty.get.nbshow}&amp;page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
							{/if}
						{/section}
						</span>
					</td>
				</tr>
			</tfoot>
			<tbody>
{foreach from=$members item=member key=ordre}
				<tr>
					<td class="{$member.class} right">{$ordre}</td>
					<td class="{$member.class} nowrap username_row">
						<input type="checkbox" name="member_sel[]" value="{$member.id_adh}"/>
					{if $member.genre eq 1}
						<img src="{$template_subdir}images/icon-male.png" alt="{_T string="[M]"}" width="16" height="16"/>
					{elseif $member.genre eq 2 || $member.genre eq 3}
						<img src="{$template_subdir}images/icon-female.png" alt="{_T string="[W]"}" width="16" height="16"/>
					{elseif $member.genre eq 4}
						<img src="{$template_subdir}images/icon-company.png" alt="{_T string="[W]"}" width="16" height="16"/>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
					{/if}
					{if $member.email != ''}
						<a href="mailto:{$member.email}"><img src="{$template_subdir}images/icon-mail.png" alt="{_T string="[Mail]"}" width="16" height="16"/></a>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
					{/if}
					{if $member.admin eq 1}
						<img src="{$template_subdir}images/icon-star.png" alt="{_T string="[admin]"}" width="16" height="16"/>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
					{/if}
					<a href="voir_adherent.php?id_adh={$member.id_adh}">{$member.nom} {$member.prenom}</a>
					</td>
					<td class="{$member.class} nowrap">{$member.pseudo|htmlspecialchars}</td>
					<td class="{$member.class} nowrap">{$member.statut}</td>
					<td class="{$member.class} nowrap">{$member.statut_cotis}</td>
					<td class="{$member.class} center nowrap actions_row">
						<a href="subscription_form.php?id_adh={$member.id_adh}"><img src="{$template_subdir}images/icon-fiche.png" alt="Fiche adhérent" width="18" height="13"/></a>
						<a href="ajouter_adherent.php?id_adh={$member.id_adh}"><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16"/></a>
						<a href="gestion_contributions.php?id_adh={$member.id_adh}"><img src="{$template_subdir}images/icon-money.png" alt="{_T string="[$]"}" width="16" height="16"/></a>
						<a onclick="return confirm('{_T string="Do you really want to delete this member from the base? This will also delete the history of his fees. You could instead disable the account.\n\nDo you still want to delete this member ?"|escape:"javascript"}')" href="gestion_adherents.php?sup={$member.id_adh}"><img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16"/></a>
					</td>
				</tr>
{foreachelse}
				<tr><td colspan="6" class="emptylist">{_T string="no member"}</td></tr>
{/foreach}
			</tbody>
		</table>
{if $nb_members != 0}
			<ul class="selection_menu">
				<li>{_T string="Selection:"}</li>
				<li><input type="submit" id="delete" class="submit" onclick="return confirm('{_T string="Do you really want to delete all selected accounts (and related contributions)?"|escape:"javascript"}');" name="delete" value="{_T string="Delete"}"/></li>
				<li><input type="submit" id="sendmail" class="submit" name="mailing" value="{_T string="Mail"}"/></li>
				<li><input type="submit" class="submit" name="labels" value="{_T string="Generate labels"}"/></li>
				<li><input type="submit" class="submit" name="cards" value="{_T string="Generate Member Cards"}"/></li>
			</ul>
{/if}
		</form>
		<div id="legende" title="{_T string="Legend"}">
			<h1>{_T string="Legend"}</h1>
			<table>
				<tr>
					<th><img src="{$template_subdir}images/icon-male.png" alt="{_T string="Mister"}" width="16" height="16"/></th>
					<td class="back">{_T string="Man"}</td>
					<th class="back">{_T string="Name"}</th>
					<td class="back">{_T string="Active account"}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-female.png" alt="{_T string="Miss"} / {_T string="Mrs"}" width="16" height="16"/></th>
					<td class="back">{_T string="Woman"}</td>
					<th class="inactif back">{_T string="Name"}</th>
					<td class="back">{_T string="Inactive account"}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-company.png" alt="{_T string="Society"}" width="16" height="16"/></th>
					<td class="back">{_T string="Society"}</td>
					<th class="cotis-never color-sample">&nbsp;</th>
					<td class="back">{_T string="Never contributed"}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-star.png" alt="{_T string="Admin"}" width="16" height="16"/></th>
					<td class="back">{_T string="Admin"}</td>
					<th class="cotis-ok color-sample">&nbsp;</th>
					<td class="back">{_T string="Membership in order"}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="Modify"}" width="16" height="16"/></th>
					<td class="back">{_T string="Modification"}</td>
					<th class="cotis-soon color-sample">&nbsp;</th>
					<td class="back">{_T string="Membership will expire soon (&lt;30d)"}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-money.png" alt="{_T string="Contribution"}" width="16" height="16"/></th>
					<td class="back">{_T string="Contributions"}</td>
					<th class="cotis-late color-sample">&nbsp;</th>
					<td class="back">{_T string="Lateness in fee"}</td>
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-trash.png" alt="{_T string="Delete"}" width="16" height="16"/></th>
					<td class="back">{_T string="Deletion"}</td>
					<!--<th></th>
					<td></td>-->
				</tr>
{if $PAGENAME eq "gestion_adherents.php"}
				<tr>
					<th><img src="{$template_subdir}images/icon-mail.png" alt="{_T string="E-mail"}" width="16" height="16"/></th>
					<td class="back">{_T string="Send a mail"}</td>
					<!--<th></th>
					<td></td>-->
				</tr>
{/if}
				{*<tr>
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
				</tr>*}
			</table>
		</div>
{if $nb_members != 0}
		<script type="text/javascript">
		//<![CDATA[
		var _is_checked = true;
		var _bind_check = function(){ldelim}
			$('#checkall').click(function(){ldelim}
				$('#listing :checkbox[name=member_sel[]]').each(function(){ldelim}
					this.checked = _is_checked; 
				{rdelim});
				_is_checked = !_is_checked;
			{rdelim});
			$('#checkinvert').click(function(){ldelim}
				$('#listing :checkbox[name=member_sel[]]').each(function(){ldelim}
					this.checked = !$(this).is(':checked'); 
				{rdelim});
			{rdelim});
		{rdelim}
		{* Use of Javascript to draw specifi elements that are not relevant is JS is inactive *}
		$(function(){ldelim}
			$('#table_footer').append('<span class="fleft"><a href="#" id="checkall">{_T string="(Un)Check all"}</a> | <a href="#" id="checkinvert">{_T string="Invert selection"}</a></span>');
			_bind_check();
			$('#nbshow').change(function() {ldelim}
				this.form.submit();
			{rdelim});
			$('#listing').after('<div class="center"><a href="#" id="show_legend">{_T string="Show legend"}</a></div>');
			$('#legende h1').remove();
			$('#legende').dialog({ldelim}
				autoOpen: false,
				modal: true,
				hide: 'fold',
				width: '40%'
			{rdelim}).dialog('close');

			$('#show_legend').click(function(){ldelim}
				$('#legende').dialog('open');
				return false;
			{rdelim});
		{rdelim});
		//]]>
		</script>
{/if}
