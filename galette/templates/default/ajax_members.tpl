		<table id="listing">
			<thead>
				<tr> 
					<th class="listing" class="id_row">#</th>
					<th class="listing left"> 
						{_T string="Name"}
						{*<a href="owners.php?tri=0" class="listing">
							{_T string="Name"}
							{if $smarty.session.tri_adh eq 0}
							{if $smarty.session.tri_adh_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>*}
					</th>
					<th class="listing left">
						{_T string="Nickname"}
						{*<a href="owners.php?tri=1" class="listing">
							{_T string="Nickname"}
							{if $smarty.session.tri_adh eq 1}
							{if $smarty.session.tri_adh_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>*}
					</th>
				</tr>
			</thead>
			{*<tfoot>
				<tr>
					<td colspan="3" class="right">
						{_T string="Pages:"}
						<span class="pagelink">
						{section name="pageLoop" start=1 loop=$nb_pages+1}
							{if $smarty.section.pageLoop.index eq $page}
								{$smarty.section.pageLoop.index}
							{else}
								<a href="owners.php?nbshow={$smarty.get.nbshow}&amp;page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
							{/if}
						{/section}
						</span>
					</td>
				</tr>
			</tfoot>*}
			<tbody>
{foreach from=$owners item=owner}
				<tr>
					<td class="right">{$owner->id}</td>
					<td class="nowrap username_row">

					{if $owner->politeness == constant('Politeness::MR')}
						<img src="{$template_subdir}images/icon-male.png" alt="{_T string="[M]"}" width="16" height="16"/>
					{elseif $owner->politeness == constant('Politeness::MRS') || $owner->politeness == constant('Politeness::MISS')}
						<img src="{$template_subdir}images/icon-female.png" alt="{_T string="[W]"}" width="16" height="16"/>
					{elseif $owner->politeness == constant('Politeness::COMPANY')}
						<img src="{$template_subdir}images/icon-company.png" alt="{_T string="[W]"}" width="16" height="16"/>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="10" height="12"/>
					{/if}
					{if $owner->isAdmin()}
						<img src="{$template_subdir}images/icon-star.png" alt="{_T string="[admin]"}" width="16" height="16"/>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="12" height="13"/>
					{/if}
					<a href="voir_adherent.php?id_adh={$owner->id}">{$owner->sfullname}</a>
					</td>
					<td class="nowrap">{$owner->nickname|htmlspecialchars}</td>
				</tr>
{foreachelse}
				<tr><td colspan="3" class="emptylist">{_T string="no member"}</td></tr>
{/foreach}
			</tbody>
		</table>
