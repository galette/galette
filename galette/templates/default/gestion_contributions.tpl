		<h1 id="titre">{_T string="Management of contributions"}</h1>
		<form action="gestion_contributions.php" method="get" id="filtre">
		<div id="listfilter">
			<label for="contrib_filter_1">{_T string="Show contributions since"}</label>&nbsp;
			<input type="text" name="contrib_filter_1" id="contrib_filter_1" maxlength="10" size="10" value="{$smarty.session.filtre_date_cotis_1}"/>
			<label for="contrib_filter_2">{_T string="until"}</label>&nbsp;
			<input type="text" name="contrib_filter_2" id="contrib_filter_2" maxlength="10" size="10" value="{$smarty.session.filtre_date_cotis_2}"/>
			<input type="submit" class="submit inline" value="{_T string="Filter"}"/>
		</div>
		<table class="infoline">
			<tr>
				<td class="left">{$nb_contributions} {if $nb_contributions != 1}{_T string="contributions"}{else}{_T string="contribution"}{/if}</td>
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
							<a href="gestion_contributions.php?nbshow={$smarty.get.nbshow}&amp;page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
						{/if}
					{/section}
					</span>
				</td>
			</tr>
		</table>
		</form>
		<table id="listing">
			<thead>
				<tr>
					<th class="listing" id="id_row">#</th>
					<th class="listing left date_row">
						<a href="gestion_contributions.php?tri=0" class="listing">{_T string="Date"}
						{if $smarty.session.tri_cotis eq 0}
						{if $smarty.session.tri_cotis_sens eq 0}
						<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
						{else}
						<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
						{/if}
						{/if}
						</a>
					</th>
					<th class="listing left date_row"> {_T string="Begin."}</th>
					<th class="listing left date_row"> {_T string="End"}</th>
{if $login->isAdmin()}
					<th class="listing left">
						<a href="gestion_contributions.php?tri=1" class="listing">{_T string="Member"}
						{if $smarty.session.tri_cotis eq 1}
						{if $smarty.session.tri_cotis_sens eq 0}
						<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
						{else}
						<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
						{/if}
						{/if}
						</a>
					</th>
{/if}
					<th class="listing left">
						<a href="gestion_contributions.php?tri=2" class="listing">{_T string="Type"}
						{if $smarty.session.tri_cotis eq 2}
						{if $smarty.session.tri_cotis_sens eq 0}
						<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
						{else}
						<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
						{/if}
						{/if}
						</a>
					</th>
					<th class="listing left">
						<a href="gestion_contributions.php?tri=3" class="listing">{_T string="Amount"}
						{if $smarty.session.tri_cotis eq 3}
						{if $smarty.session.tri_cotis_sens eq 0}
						<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
						{else}
						<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
						{/if}
						{/if}
						</a>
					</th>
					<th class="listing left">
						<a href="gestion_contributions.php?tri=4" class="listing">{_T string="Duration"}
						{if $smarty.session.tri_cotis eq 4}
						{if $smarty.session.tri_cotis_sens eq 0}
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
			<tbody>
{foreach from=$contributions item=contribution key=ordre}
				<tr>
					<td class="{$contribution.class} center nowrap">{$ordre}</td>
					<td class="{$contribution.class} nowrap">{$contribution.date_enreg}</td>
					<td class="{$contribution.class} nowrap">{$contribution.date_debut}</td>
					<td class="{$contribution.class} nowrap">{$contribution.date_fin}</td>
{if $login->isAdmin()}
					<td class="{$contribution.class}">
{if $smarty.session.filtre_cotis_adh eq ""}
						<a href="gestion_contributions.php?id_adh={$contribution.id_adh}">
							{$contribution.nom} {$contribution.prenom}
						</a>
{else}
						<a href="voir_adherent.php?id_adh={$contribution.id_adh}">
							{$contribution.nom} {$contribution.prenom}
						</a>
{/if}
					</td>
{/if}
					<td class="{$contribution.class}">{$contribution.libelle_type_cotis}</td>
					<td class="{$contribution.class} nowrap">{$contribution.montant_cotis}</td>
					<td class="{$contribution.class} nowrap">{$contribution.duree_mois_cotis}</td>
{if $login->isAdmin()}
					<td class="{$contribution.class} center nowrap">
						<a href="ajouter_contribution.php?id_cotis={$contribution.id_cotis}"><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16"/></a>
						<a onclick="return confirm('{_T string="Do you really want to delete this contribution of the database ?"|escape:"javascript"}')" href="gestion_contributions.php?sup={$contribution.id_cotis}"><img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16"/></a>
					</td>
{/if}
				</tr>
{foreachelse}
{if $login->isAdmin()}
				<tr><td colspan="9" class="emptylist">{_T string="no contribution"}</td></tr>
{else}
				<tr><td colspan="7" class="emptylist">{_T string="no contribution"}</td></tr>
{/if}
{/foreach}
			</tbody>
		</table>
		<div class="infoline2 right">
			{_T string="Pages:"}
			<span class="pagelink">
			{section name="pageLoop" start=1 loop=$nb_pages+1}
			{if $smarty.section.pageLoop.index eq $page}
			{$smarty.section.pageLoop.index}
			{else}
			<a href="gestion_contributions.php?nbshow={$smarty.get.nbshow}&amp;page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
			{/if}
			{/section}
			</span>
		</div>
{if $smarty.session.filtre_cotis_adh!=""}
		<br/>
		<div align="center">
			<table class="{$statut_class}">
				<tr>
					<td>{$statut_cotis}</td>
				</tr>
			</table>
		<br/>
{if $login->isAdmin()}
		<br/>
			<a href="voir_adherent.php?id_adh={$smarty.session.filtre_cotis_adh}">{_T string="[ See member profile ]"}</a>
			&nbsp;&nbsp;&nbsp;
			<a href="ajouter_contribution.php?&amp;id_adh={$smarty.session.filtre_cotis_adh}">{_T string="[ Add a contribution ]"}</a>
{/if}
		</div>
{/if}
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
			//<![CDATA[
			$(function(){ldelim}
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