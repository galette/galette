		<h1 class="titre">{_T string="Logs"}</h1>
		<div class="button-container">
			<div class="button-link button-flush-logs">
				<a href="log.php?reset=1">{_T string="Flush the logs"}</a>
			</div>
		</div>
		<table class="infoline" width="100%" border="0">
			<tr>
				<td class="left">{$nb_lines} {if $nb_lines != 1}{_T string="lines"}{else}{_T string="line"}{/if}</td>
			</tr>
		</table>
		<table width="100%" border="0">
			<tr>
				<th width="15" class="listing">#</th>
				<th class="listing left" width="150">
					<a href="log.php?tri=0" class="listing">{_T string="Date"}</a>
					{if $smarty.session.tri_log eq 0}
					{if $smarty.session.tri_log_sens eq 0}
					<img src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<img src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<img src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</th>
				<th class="listing left" width="150">
					<a href="log.php?tri=1" class="listing">{_T string="IP"}</a>
					{if $smarty.session.tri_log eq 1}
					{if $smarty.session.tri_log_sens eq 0}
					<img src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<img src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<img src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</th>
				<th class="listing left" width="150">
					<a href="log.php?tri=2" class="listing">{_T string="User"}</a>
					{if $smarty.session.tri_log eq 2}
					{if $smarty.session.tri_log_sens eq 0}
					<img src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<img src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<img src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</th>
				<th class="listing left" width="150">
					<a href="log.php?tri=4" class="listing">{_T string="Action"}</a>
					{if $smarty.session.tri_log eq 4}
					{if $smarty.session.tri_log_sens eq 0}
					<img src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<img src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<img src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</th>
				<th class="listing left">
					<a href="log.php?tri=3" class="listing">{_T string="Description"}</a>
					{if $smarty.session.tri_log eq 3}
					{if $smarty.session.tri_log_sens eq 0}
					<img src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<img src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<img src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</th>
			</tr>
{foreach from=$logs item=log key=ordre}
			<tr class="cotis-never">
				<td width="15" valign="top">{$ordre}</td>
				<td valign="top" nowrap="nowrap">{$log.date}</td>
				<td valign="top" nowrap="nowrap">{$log.ip}</td>
				<td valign="top" nowrap="nowrap">{$log.adh}</td>
				<td valign="top" nowrap="nowrap">{$log.action}</td>
				<td valign="top">{$log.login}<br/>{$log.desc|htmlspecialchars}</td>
			</tr>

{foreachelse}
			<tr><td colspan="6" class="emptylist">{_T string="logs are empty"}</td></tr>
{/foreach}
			<tr>
				<td colspan="6" class="center" id="table_footer">
					{_T string="Pages:"}<br/>
					<ul class="pages">{$pagination}</ul>
				</td>
			</tr>
		</table>
