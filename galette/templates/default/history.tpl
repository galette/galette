		<h1 id="titre">{_T("Logs")}</h1>
		<div class="button-container">
			<div class="button-link button-flush-logs">
				<a href="history.php?reset=1">{_T("Flush the logs")}</a>
			</div>
		</div>
		<table id="listing">
			<thead>
				<tr>
					<td colspan="6" class="right">
						<span class="fleft">{$nb_lines} {if $nb_lines != 1}{_T("lines")}{else}{_T("line")}{/if}</span>
						{_T("Pages:")}
						<span class="pagelink">
{section name="pageLoop" start=1 loop=$nb_pages+1}
{if $smarty.section.pageLoop.index eq $page}
						{$smarty.section.pageLoop.index}
{else}
						<a href="history.php?page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
{/if}
{/section}
						</span>
					</td>
				</tr>
				<tr>
					<th class="listing">#</th>
					<th class="listing left" width="150">
						<a href="history.php?tri=0" class="listing">
							{_T("Date")}
							{if $smarty.session.tri_log eq 0}
							{if $smarty.session.tri_log_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>
					</th>
					<th class="listing left" width="150">
						<a href="history.php?tri=1" class="listing">
							{_T("IP")}
							{if $smarty.session.tri_log eq 1}
							{if $smarty.session.tri_log_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>
					</th>
					<th class="listing left" width="150">
						<a href="history.php?tri=2" class="listing">
							{_T("User")}
							{if $smarty.session.tri_log eq 2}
							{if $smarty.session.tri_log_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>
					</th>
					<th class="listing left" width="150">
						<a href="history.php?tri=4" class="listing">
							{_T("Action")}
							{if $smarty.session.tri_log eq 4}
							{if $smarty.session.tri_log_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>
					</th>
					<th class="listing left">
						<a href="history.php?tri=3" class="listing">
							{_T("Description")}
							{if $smarty.session.tri_log eq 3}
							{if $smarty.session.tri_log_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>
					</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="6" class="right">
						{_T("Pages:")}
						<span class="pagelink">
						{section name="pageLoop" start=1 loop=$nb_pages+1}
						{if $smarty.section.pageLoop.index eq $page}
						{$smarty.section.pageLoop.index}
						{else}
						<a href="history.php?page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
						{/if}
						{/section}
						</span>
					</td>
				</tr>
			</tfoot>
			<tbody>
{foreach from=$logs item=log key=ordre}
				<tr class="cotis-never">
					<td width="15" valign="top">{$ordre}</td>
					<td valign="top" nowrap="nowrap">{$log.date}</td>
					<td valign="top" nowrap="nowrap">{$log.ip}</td>
					<td valign="top" nowrap="nowrap">{$log.adh}</td>
					<td valign="top" nowrap="nowrap">{$log.action}</td>
					<td valign="top">{$log.desc}</td>
				</tr>
{foreachelse}
				<tr><td colspan="6" class="emptylist">{_T("logs are empty")}</td></tr>
{/foreach}
			</tbody>
		</table>