{if $members|@count > 0}
        <p>{_T string="This page shows only members who have choosen to be visible on the public lists and are up-to-date within their contributions. If you want your account to be visible here, edit your profile and check 'Be visible in the members list'"}</p>
		<table id="listing">
			<thead>
				<tr>
					<th class="listing left">
						<a href="?tri={php}echo Members::ORDERBY_NAME;{/php}" class="listing">
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
						<a href="?tri={php}echo Members::ORDERBY_NICKNAME;{/php}" class="listing">
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
						<a href="?tri=infos" class="listing">{_T string="Informations"}</a>
					</th>
				</tr>
			</thead>
			<tbody>
	{foreach from=$members item=member}
				<tr>
					<td class="{$member->getRowClass(true)} nowrap username_row">
                    {if $member->isCompany()}
						<img src="{$template_subdir}images/icon-company.png" alt="" width="16" height="16"/>
					{elseif $member->politeness == constant('Politeness::MR')}
						<img src="{$template_subdir}images/icon-male.png" alt="" width="16" height="16"/>
					{elseif $member->politeness == constant('Politeness::MRS') || $member->politeness == constant('Politeness::MISS')}
						<img src="{$template_subdir}images/icon-female.png" alt="" width="16" height="16"/>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="10" height="12"/>
					{/if}

					{if $member->website ne ''}
						<a href="{$member->website}">{$member->sfullname}</a>
					{else}
						{$member->sfullname}
					{/if}
					</td>
					<td class="{$member->getRowClass(true)} nowrap">{$member->nickname|htmlspecialchars}</td>
					<td class="{$member->getRowClass(true)} nowrap">{$member->others_infos}</td>
				</tr>
	{/foreach}
			</tbody>
		</table>
{else}
	<div id="infobox">{_T string="No member to show"}</div>
{/if}