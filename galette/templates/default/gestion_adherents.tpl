		<form action="gestion_adherents.php" method="get" id="filtre">
		<h1 id="titre">{_T string="Members management"}</h1>
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
			<label for="filter_str">{_T string="Search:"}&nbsp;</label>
			<input type="text" name="filter_str" id="filter_str" value="{$varslist->filter_str}"/>&nbsp;
		 	{_T string="in:"}&nbsp;
			<select name="filter_field">
				{html_options options=$filter_field_options selected=$varslist->field_filter}
			</select>
		 	{_T string="among:"}&nbsp;
			<select name="filter_membership" onchange="form.submit()">
				{html_options options=$filter_membership_options selected=$varslist->membership_filter}
			</select>
			<select name="filter_account" onchange="form.submit()">
				{html_options options=$filter_accounts_options selected=$varslist->account_status_filter}
			</select>
			<input type="submit" class="submit inline" value="{_T string="Filter"}"/>
			<input type="submit" name="clear_filter" class="submit inline" value="{_T string="Clear filter"}"/>
		</div>
		<table class="infoline">
			<tr>
				<td class="left">{$nb_members} {if $nb_members != 1}{_T string="members"}{else}{_T string="member"}{/if}</td>
				<td class="right">
					<label for="nbshow">{_T string="Records per page:"}</label>
					<select name="nbshow" id="nbshow">
						{html_options options=$nbshow_options selected=$numrows}
					</select>
					<noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
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
						<a href="gestion_adherents.php?tri={php}echo Members::ORDERBY_NAME;{/php}" class="listing">
							{_T string="Name"}
							{if $varslist->orderby eq constant('Members::ORDERBY_NAME')}
								{if $varslist->ordered eq constant('VarsList::ORDER_ASC')}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
								{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
								{/if}
							{/if}
						</a>
					</th>
					<th class="listing left">
						<a href="gestion_adherents.php?tri={php}echo Members::ORDERBY_NICKNAME;{/php}" class="listing">
							{_T string="Nickname"}
							{if $varslist->orderby eq constant('Members::ORDERBY_NICKNAME')}
								{if $varslist->ordered eq constant('VarsList::ORDER_ASC')}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
								{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
								{/if}
							{/if}
						</a>
					</th>
					<th class="listing left">
						<a href="gestion_adherents.php?tri={php}echo Members::ORDERBY_STATUS;{/php}" class="listing">
							{_T string="Status"}
							{if $varslist->orderby eq constant('Members::ORDERBY_STATUS')}
								{if $varslist->ordered eq constant('VarsList::ORDER_ASC')}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
								{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
								{/if}
							{/if}
						</a>
					</th>
					<th class="listing left">
						<a href="gestion_adherents.php?tri={php}echo Members::ORDERBY_FEE_STATUS;{/php}" class="listing">
							{_T string="State of dues"}
							{if $varslist->orderby eq constant('Members::ORDERBY_FEE_STATUS')}
								{if $varslist->ordered eq constant('VarsList::ORDER_ASC')}
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
					<td colspan="6" class="center" id="table_footer">
						{_T string="Pages:"}<br/>
						<ul class="pages">{$pagination}</ul>
					</td>
				</tr>
			</tfoot>
			<tbody>
{foreach from=$members item=member key=ordre}
				<tr>
					<td class="{$member->getRowClass()} right">{php}$ordre = $this->get_template_vars('ordre');echo $ordre+1+($varslist->current_page - 1)*$numrows{/php}</td>
					<td class="{$member->getRowClass()} nowrap username_row">
						<input type="checkbox" name="member_sel[]" value="{$member->id}"/>
					{if $member->politeness eq constant('Politeness::MR')}
						<img src="{$template_subdir}images/icon-male.png" alt="{_T string="[M]"}" width="16" height="16"/>
					{elseif $member->politeness eq constant('Politeness::MRS') || $member->politeness eq constant('Politeness::MISS')}
						<img src="{$template_subdir}images/icon-female.png" alt="{_T string="[W]"}" width="16" height="16"/>
					{elseif $member->politeness eq constant('Politeness::COMPANY')}
						<img src="{$template_subdir}images/icon-company.png" alt="{_T string="[W]"}" width="16" height="16"/>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
					{/if}
					{if $member->email != ''}
						<a href="mailto:{$member->email}"><img src="{$template_subdir}images/icon-mail.png" alt="{_T string="[Mail]"}" width="16" height="16"/></a>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
					{/if}
					{if $member->isAdmin()}
						<img src="{$template_subdir}images/icon-star.png" alt="{_T string="[admin]"}" width="16" height="16"/>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
					{/if}
					<a href="voir_adherent.php?id_adh={$member->id}">{$member->sname}</a>
					</td>
					<td class="{$member->getRowClass()} nowrap">{$member->nickname|htmlspecialchars}</td>
					<td class="{$member->getRowClass()} nowrap">{$member->sstatus}</td>
					<td class="{$member->getRowClass()}">{$member->getDues()}</td>
					<td class="{$member->getRowClass()} center nowrap actions_row">
						<a href="subscription_form.php?id_adh={$member->id}"><img src="{$template_subdir}images/icon-fiche.png" alt="Fiche adhérent" width="18" height="13" title="{php}$member = $this->get_template_vars('member'); echo preg_replace('/%membername/', $member->sname, _T("%membername: PDF member card"));{/php}"/></a>
						<a href="ajouter_adherent.php?id_adh={$member->id}"><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16" title="{php}$member = $this->get_template_vars('member'); echo preg_replace('/%membername/', $member->sname, _T("%membername: edit informations"));{/php}"/></a>
						<a href="gestion_contributions.php?id_adh={$member->id}"><img src="{$template_subdir}images/icon-money.png" alt="{_T string="[$]"}" width="16" height="16" title="{php}$member = $this->get_template_vars('member'); echo preg_replace('/%membername/', $member->sname, _T("%membername: contributions"));{/php}"/></a>
						<a onclick="return confirm('{_T string="Do you really want to delete this member from the base? This will also delete the history of his fees. You could instead disable the account.\n\nDo you still want to delete this member ?"|escape:"javascript"}')" href="gestion_adherents.php?sup={$member->id}"><img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16" title="{php}$member = $this->get_template_vars('member'); echo preg_replace('/%membername/', $member->sname, _T("%membername: remove from database"));{/php}"/></a>
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
{if $nb_members != 0}
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
				</tr>
				<tr>
					<th><img src="{$template_subdir}images/icon-mail.png" alt="{_T string="E-mail"}" width="16" height="16"/></th>
					<td class="back">{_T string="Send a mail"}</td>
				</tr>
			</table>
		</div>
		<script type="text/javascript">
		//<![CDATA[
		var _is_checked = true;
		var _bind_check = function(){ldelim}
			$('#checkall').click(function(){ldelim}
				$('#listing :checkbox[name=member_sel[]]').each(function(){ldelim}
					this.checked = _is_checked;
				{rdelim});
				_is_checked = !_is_checked;
				return false;
			{rdelim});
			$('#checkinvert').click(function(){ldelim}
				$('#listing :checkbox[name=member_sel[]]').each(function(){ldelim}
					this.checked = !$(this).is(':checked');
				{rdelim});
				return false;
			{rdelim});
		{rdelim}
		{* Use of Javascript to draw specific elements that are not relevant is JS is inactive *}
		$(function(){ldelim}
			$('#table_footer').parent().before('<tr><td id="checkboxes" colspan="4"><span class="fleft"><a href="#" id="checkall">{_T string="(Un)Check all"}</a> | <a href="#" id="checkinvert">{_T string="Invert selection"}</a></span></td></tr>');
			_bind_check();
			$('#nbshow').change(function() {ldelim}
				this.form.submit();
			{rdelim});
			$('#checkboxes').after('<td class="right" colspan="2"><a href="#" id="show_legend">{_T string="Show legend"}</a></td>');
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
