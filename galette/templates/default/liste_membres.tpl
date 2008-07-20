		<h1 id="titre">{_T string="Members list"}</h1>
		<table id="listing">
			<thead>
				<tr>
					<th class="listing left">
						<a href="?tri=name" class="listing">
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
						<a href="?tri=nickname" class="listing">
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
						<a href="?tri=infos" class="listing">
							{_T string="Informations"}
							{if $smarty.session.tri_adh eq 2}
							{if $smarty.session.tri_adh_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>
					</th>
				</tr>
			</thead>
			<tbody>
{foreach from=$members item=member key=ordre}
				<tr>
					<td class="{$member.class} nowrap username_row">
					{if $member.genre eq 1}
						<img src="{$template_subdir}images/icon-male.png" alt="{_T string="Mr."}" width="16" height="16"/>
					{elseif $member.genre eq 2 || $member.genre eq 3}
						<img src="{$template_subdir}images/icon-female.png" alt="{_T string="Mrs."}" width="16" height="16"/>
					{elseif $member.genre eq 4}
						<img src="{$template_subdir}images/icon-company.png" alt="" width="16" height="16"/>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="10" height="12"/>
					{/if}
					{if $member.url ne ''}
						<a href="{$member.url}">{$member.nom} {$member.prenom}</a>
					{else}
						{$member.nom} {$member.prenom}
					{/if}
					</td>
					<td class="{$member.class} nowrap">{$member.pseudo}</td>
					<td class="{$member.class} nowrap">{$member.infos}</td>
				</tr>
{foreachelse}
				<tr><td colspan="6" class="emptylist">{_T string="There is no member with public informations available."}</td></tr>
{/foreach}
			</tbody>
		</table>