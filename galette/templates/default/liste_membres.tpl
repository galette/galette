        <div id="main_logo">
            <img src="{$galette_base_path}picture.php?logo=true" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" />
        </div>
		<h1 id="titre">{_T string="Members list"}</h1>
		<ul class="menu m_subscribe">
			<li id="backhome"><a href="../index.php">{_T string="Back to login page"}</a></li>
			<li id="trombino"><a href="trombinoscope.php">{_T string="Trombinoscope"}</a></li>
		</ul>
{if $members|@count > 0}
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
	{foreach from=$members item=member}
				<tr>
					<td class="{$member->getRowClass(true)} nowrap username_row">
					{if $member->politeness == constant('Politeness::MR')}
						<img src="{$template_subdir}images/icon-male.png" alt="" width="16" height="16"/>
					{elseif $member->politeness == constant('Politeness::MRS') || $member->politeness == constant('Politeness::MISS')}
						<img src="{$template_subdir}images/icon-female.png" alt="" width="16" height="16"/>
					{elseif $member->politeness == constant('Politeness::COMPANY')}
						<img src="{$template_subdir}images/icon-company.png" alt="" width="16" height="16"/>
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