		<h1 class="titre">{_T("Management of contributions")}</h1>
		<form action="gestion_contributions.php" method="get" name="filtre">
		<div id="listfilter">
			{_T("Show contributions since")}&nbsp;
			<input type="text" name="contrib_filter_1" maxlength="10" size="10" value="{$smarty.session.filtre_date_cotis_1}"/>
			{_T("until")}&nbsp;
			<input type="text" name="contrib_filter_2" maxlength="10" size="10" value="{$smarty.session.filtre_date_cotis_2}"/>
			<input type="submit" value="{_T("Filter")}"/>
		</div>
		<table class="infoline" width="100%">
			<tr>
				<td class="left">{$nb_contributions} {if $nb_contributions != 1}{_T("contributions")}{else}{_T("contribution")}{/if}</td>
                                <td class="center">
					{_T("Show:")}
					<select name="nbshow" onChange="form.submit()">
						{html_options options=$nbshow_options selected=$numrows}
					</select>
				</td>
				<td class="right">{_T("Pages:")}
					<span class="pagelink">
					{section name="pageLoop" start=1 loop=$nb_pages+1}
						{if $smarty.section.pageLoop.index eq $page}
							{$smarty.section.pageLoop.index}
						{else}
							<a href="gestion_contributions.php?nbshow={$smarty.get.nbshow}&page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
						{/if}
					{/section}
					</span>
				</td>
			</tr>
		</table>
		</form>
		<table width="100%">
			<tr>
				<th width="15" class="listing">#</th>
				<th class="listing left">
					<a href="gestion_contributions.php?tri=0" class="listing">{_T("Date")}</a>
					{if $smarty.session.tri_cotis eq 0}
					{if $smarty.session.tri_cotis_sens eq 0}
					<img src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<img src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<img src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</th>
				<th class="listing left"> {_T("Begin.")}</th>
				<th class="listing left"> {_T("End")}</th>
{if $smarty.session.admin_status eq 1}
				<th class="listing left">
					<a href="gestion_contributions.php?tri=1" class="listing">{_T("Member")}</a>
					{if $smarty.session.tri_cotis eq 1}
					{if $smarty.session.tri_cotis_sens eq 0}
					<img src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<img src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<img src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</th>
{/if}
				<th class="listing left">
					<a href="gestion_contributions.php?tri=2" class="listing">{_T("Type")}</a>
					{if $smarty.session.tri_cotis eq 2}
					{if $smarty.session.tri_cotis_sens eq 0}
					<img src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<img src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<img src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</th>
				<th class="listing left">
					<a href="gestion_contributions.php?tri=3" class="listing">{_T("Amount")}</a>
					{if $smarty.session.tri_cotis eq 3}
					{if $smarty.session.tri_cotis_sens eq 0}
					<img src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<img src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<img src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</th>
				<th class="listing left">
					<a href="gestion_contributions.php?tri=4" class="listing">{_T("Duration")}</a>
					{if $smarty.session.tri_cotis eq 4}
					{if $smarty.session.tri_cotis_sens eq 0}
					<img src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<img src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<img src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</th>
{if $smarty.session.admin_status eq 1}
				<th width="55" class="listing">{_T("Actions")}</th>
{/if}
			</tr>
{foreach from=$contributions item=contribution key=ordre}
			<tr>
				<td width="15" class="{$contribution.class} center" nowrap="nowrap">{$ordre}</td>
				<td width="50" class="{$contribution.class}" nowrap="nowrap">
					{$contribution.date_enreg}
				</td>
				<td width="50" class="{$contribution.class}" nowrap="nowrap">
					{$contribution.date_debut}
				</td>
				<td width="50" class="{$contribution.class}" nowrap="nowrap">
					{$contribution.date_fin}
				</td>
{if $smarty.session.admin_status eq 1}
			<td class="{$contribution.class}" nowrap="nowrap">
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
			<td class="{$contribution.class}" nowrap="nowrap">{$contribution.libelle_type_cotis}</td>
			<td class="{$contribution.class}" nowrap="nowrap">{$contribution.montant_cotis}</td>
			<td class="{$contribution.class}" nowrap="nowrap">{$contribution.duree_mois_cotis}</td>
{if $smarty.session.admin_status eq 1}
			<td width="55" class="{$contribution.class} center" nowrap="nowrap">
				<a href="ajouter_contribution.php?id_cotis={$contribution.id_cotis}"><img src="{$template_subdir}images/icon-edit.png" alt="{_T("[mod]")}" border="0" width="12" height="13"/></a>
				<a onClick="return confirm('{_T("Do you really want to delete this contribution of the database ?")|escape:"javascript"}')" href="gestion_contributions.php?sup={$contribution.id_cotis}"><img src="{$template_subdir}images/icon-trash.png" alt="{_T("[del]")}" border="0" width="11" height="13"/></a>
			</td>
{/if}
		</tr>
{foreachelse}
{if $smarty.session.admin_status eq 1}
			<tr><td colspan="9" class="emptylist">{_T("no contribution")}</td></tr>
{else}
			<tr><td colspan="7" class="emptylist">{_T("no contribution")}</td></tr>
{/if}
{/foreach}
		</table>
		<div class="infoline2 right">
			{_T("Pages:")}
			<span class="pagelink">
			{section name="pageLoop" start=1 loop=$nb_pages+1}
			{if $smarty.section.pageLoop.index eq $page}
			{$smarty.section.pageLoop.index}
			{else}
			<a href="gestion_contributions.php?nbshow={$smarty.get.nbshow}&page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
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
{if $smarty.session.admin_status eq 1}
		<br/>
			<a href="voir_adherent.php?id_adh={$smarty.session.filtre_cotis_adh}">{_T("[ See member profile ]")}</a>
			&nbsp;&nbsp;&nbsp;
			<a href="ajouter_contribution.php?&amp;id_adh={$smarty.session.filtre_cotis_adh}">{_T("[ Add a contribution ]")}</a>
{/if}
		</div>
{/if}
