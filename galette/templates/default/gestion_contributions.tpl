		<h1 id="titre">{_T string="Management of contributions"}</h1>
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
		<form action="gestion_contributions.php" method="get" id="filtre">
		<div id="listfilter">
			<label for="start_date_filter">{_T string="Show contributions since"}</label>&nbsp;
			<input type="text" name="start_date_filter" id="start_date_filter" maxlength="10" size="10" value="{$contributions->start_date_filter}"/>
			<label for="end_date_filter">{_T string="until"}</label>&nbsp;
			<input type="text" name="end_date_filter" id="end_date_filter" maxlength="10" size="10" value="{$contributions->end_date_filter}"/>
			<input type="submit" class="inline" value="{_T string="Filter"}"/>
			<input type="submit" name="clear_filter" class="inline" value="{_T string="Clear filter"}"/>
		</div>
{if $member}
		<div align="center">
            <p class="{$member->getRowClass()}">{$member->getDues()}</p>
		</div>
{/if}
		<table class="infoline">
			<tr>
				<td class="left nowrap">
{if $member}
    {if $login->isAdmin()}
                    <a id="clearfilter" href="?id_adh=all" title="{_T string="Show all members contributions"}">{_T string="Show all members contributions"}</a>
    {/if}
                    <strong>{$member->sname}</strong>
    {if $login->isAdmin()}
                    (<a href="voir_adherent.php?id_adh={$contribs->filtre_cotis_adh}">{_T string="See member profile"}</a> -
                    <a href="ajouter_contribution.php?id_adh={$member->id}">{_T string="Add a contribution"}</a>)
    {/if}
                    &nbsp;:
{/if}
                    {$nb_contributions} {if $nb_contributions != 1}{_T string="contributions"}{else}{_T string="contribution"}{/if}
                </td>
                <td class="right">
					<label for="nbshow">{_T string="Show:"}</label>
					<select name="nbshow" id="nbshow">
						{html_options options=$nbshow_options selected=$numrows}
					</select>
					<noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
				</td>
			</tr>
		</table>
		</form>
		<form action="gestion_contributions.php" method="post" id="listform">
		<table id="listing">
			<thead>
				<tr>
					<th class="listing id_row">#</th>
					<th class="listing left date_row">
						<a href="gestion_contributions.php?tri={php}echo Contributions::ORDERBY_DATE;{/php}" class="listing">{_T string="Date"}
                        {if $contributions->orderby eq constant('Contributions::ORDERBY_DATE')}
                            {if $contributions->ordered eq constant('Contributions::ORDER_ASC')}
						<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
						<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
						{/if}
						</a>
					</th>
					<th class="listing left date_row">
						<a href="gestion_contributions.php?tri={php}echo Contributions::ORDERBY_BEGIN_DATE;{/php}" class="listing">{_T string="Begin"}
                        {if $contributions->orderby eq constant('Contributions::ORDERBY_BEGIN_DATE')}
                            {if $contributions->ordered eq constant('Contributions::ORDER_ASC')}
						<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
						<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
						{/if}
						</a>
					</th>
					<th class="listing left date_row">
						<a href="gestion_contributions.php?tri={php}echo Contributions::ORDERBY_END_DATE;{/php}" class="listing">{_T string="End"}
                        {if $contributions->orderby eq constant('Contributions::ORDERBY_END_DATE')}
                            {if $contributions->ordered eq constant('Contributions::ORDER_ASC')}
						<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
						<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
						{/if}
						</a>
					</th>
{if $login->isAdmin() and !$member}
					<th class="listing left">
						<a href="gestion_contributions.php?tri={php}echo Contributions::ORDERBY_MEMBER;{/php}" class="listing">{_T string="Member"}
                        {if $contributions->orderby eq constant('Contributions::ORDERBY_MEMBER')}
                            {if $contributions->ordered eq constant('Contributions::ORDER_ASC')}
						<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
						<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
						{/if}
						</a>
					</th>
{/if}
					<th class="listing left">
						<a href="gestion_contributions.php?tri={php}echo Contributions::ORDERBY_TYPE;{/php}" class="listing">{_T string="Type"}
                        {if $contributions->orderby eq constant('Contributions::ORDERBY_TYPE')}
                            {if $contributions->ordered eq constant('Contributions::ORDER_ASC')}
						<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
						<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
						{/if}
						</a>
					</th>
					<th class="listing left">
						<a href="gestion_contributions.php?tri={php}echo Contributions::ORDERBY_AMOUNT;{/php}" class="listing">{_T string="Amount"}
                        {if $contributions->orderby eq constant('Contributions::ORDERBY_AMOUNT')}
                            {if $contributions->ordered eq constant('Contributions::ORDER_ASC')}
						<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
						<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
						{/if}
						</a>
					</th>
					<th class="listing left">
						<a href="gestion_contributions.php?tri={php}echo Contributions::ORDERBY_DURATION;{/php}" class="listing">{_T string="Duration"}
                        {if $contributions->orderby eq constant('Contributions::ORDERBY_DURATION')}
                            {if $contributions->ordered eq constant('Contributions::ORDER_ASC')}
						<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                            {else}
						<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                            {/if}
						{/if}
						</a>
					</th>
{if $login->isAdmin()}
					<th class="listing nowrap actions_row">{_T string="Actions"}</th>
{/if}
				</tr>
			</thead>
			<tfoot>
{if $login->isAdmin()}
                <tr>
                    <td colspan="7" id="table_footer">
                        <ul class="selection_menu">
                            <li>{_T string="For the selection:"}</li>
                            <li><input type="submit" id="delete" onclick="return confirm('{_T string="Do you really want to delete all selected contributions?"|escape:"javascript"}');" name="delete" value="{_T string="Delete"}"/></li>
                        </ul>
                    </td>
                </tr>
{/if}
				<tr>
					<td colspan="{if $login->isAdmin() && !$member}9{elseif $login->isAdmin()}8{else}7{/if}" class="center" id="table_footer">
						{_T string="Pages:"}<br/>
						<ul class="pages">{$pagination}</ul>
					</td>
				</tr>
			</tfoot>
			<tbody>
{foreach from=$list_contribs item=contribution key=ordre}
	{assign var="mid" value=$contribution->member}
	{assign var="cclass" value=$contribution->getRowClass()}
				<tr>
					<td class="{$cclass} center nowrap">
                        <input type="checkbox" name="contrib_sel[]" value="{$contribution->id}"/>
                        {php}$ordre = $this->get_template_vars('ordre');echo $ordre+1{/php}
                    </td>
					<td class="{$cclass} center nowrap">{$contribution->date}</td>
					<td class="{$cclass} center nowrap">{$contribution->begin_date}</td>
					<td class="{$cclass} center nowrap">{$contribution->end_date}</td>
	{if $login->isAdmin() && !$member}
					<td class="{$cclass}">
		{if $contribs->filtre_cotis_adh eq ""}
						<a href="gestion_contributions.php?id_adh={$mid}">{if $member}{$member->sname}{else}{memberName id="$mid"}{/if}</a>
		{else}
						<a href="voir_adherent.php?id_adh={$mid}">{if $member}{$member->sname}{else}{memberName id="$mid"}{/if}</a>
		{/if}
					</td>
	{/if}
					<td class="{$cclass}">{$contribution->type->libelle}</td>
					<td class="{$cclass} nowrap">{$contribution->amount}</td>
					<td class="{$cclass} nowrap">{$contribution->duration}</td>
	{if $login->isAdmin()}
					<td class="{$cclass} center nowrap">
						<a href="ajouter_contribution.php?id_cotis={$contribution->id}">
                            <img src="{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16" title="{_T string="Edit the contribution"}"/>
                        </a>
						<a onclick="return confirm('{_T string="Do you really want to delete this contribution of the database ?"|escape:"javascript"}')" href="gestion_contributions.php?sup={$contribution->id}">
                            <img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16" title="{_T string="Delete the contribution"}"/>
                        </a>
					</td>
	{/if}
				</tr>
{foreachelse}
				<tr><td colspan="{if $login->isAdmin() && !$member}9{elseif $login->isAdmin()}8{else}7{/if}" class="emptylist">{_T string="no contribution"}</td></tr>
{/foreach}
			</tbody>
		</table>
        </form>
		<div id="legende" title="{_T string="Legend"}">
			<h1>{_T string="Legend"}</h1>
			<table>
{if $login->isAdmin()}
				<tr>
					<th class="back"><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16"/></th>
					<td class="back">{_T string="Modification"}</td>
				</tr>
				<tr>
					<th class="back"><img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16"/></th>
					<td class="back">{_T string="Deletion"}</td>
				</tr>
{/if}
				<tr>
					<th class="cotis-normal color-sample">&nbsp;</th>
					<td class="back">{_T string="Contribution"}</td>
				</tr>
				<tr>
					<th class="cotis-give color-sample">&nbsp;</th>
					<td class="back">{_T string="Gift"}</td>
				</tr>
			</table>
		</div>

		<script type="text/javascript">
			$(function(){ldelim}
				$('#nbshow').change(function() {ldelim}
					this.form.submit();
				{rdelim});
				$('#table_footer').parent().before('<td class="right" colspan="{if $login->isAdmin() && !$member}9{elseif $login->isAdmin()}8{else}7{/if}"><a href="#" id="show_legend">{_T string="Show legend"}</a></td>');
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

                $.datepicker.setDefaults($.datepicker.regional['{$galette_lang}']);
				$('#start_date_filter').datepicker({ldelim}
					changeMonth: true,
					changeYear: true,
					showOn: 'button',
					buttonImage: '{$template_subdir}images/calendar.png',
					buttonImageOnly: true
				{rdelim});
				$('#end_date_filter').datepicker({ldelim}
					changeMonth: true,
					changeYear: true,
					showOn: 'button',
					buttonImage: '{$template_subdir}images/calendar.png',
					buttonImageOnly: true
				{rdelim});
			{rdelim});
		</script>