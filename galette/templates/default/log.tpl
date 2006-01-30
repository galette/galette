		<h1 class="titre">{_T("Logs")}</h1>
		<form action="log.php" method="post">
			<div align="center"><input type="submit" value="{_T("Flush the logs")}"/></div>
			<input type="hidden" name="reset" value="1"/>
		</form>
		<table class="infoline" width="100%" border="0">
			<tr>
				<td class="left">{$nb_lines} {if $nb_lines != 1}{_T("lines")}{else}{_T("line")}{/if}</td>
				<td class="right">
					{_T("Pages:")}
					<span class="pagelink">
{section name="pageLoop" start=1 loop=$nb_pages+1}
{if $smarty.section.pageLoop.index eq $page}
					{$smarty.section.pageLoop.index}
{else}
					<a href="log.php?page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
{/if}
{/section}
					</span>
				</td>
			</tr>
		</table>
		<table width="100%" border="0"> 
			<tr>
				<th width="15" class="listing">#</th> 
				<th class="listing left" width="150">
					<a href="log.php?tri=0" class="listing">{_T("Date")}</a>
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
					<a href="log.php?tri=1" class="listing">{_T("IP")}</a>
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
					<a href="log.php?tri=2" class="listing">{_T("User")}</a>
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
					<a href="log.php?tri=4" class="listing">{_T("Action")}</a>
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
					<a href="log.php?tri=3" class="listing">{_T("Description")}</a>
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
				<td valign="top" nowrap>{$log.date}</td>
				<td valign="top" nowrap>{$log.ip}</td>
				<td valign="top" nowrap>{$log.adh}</td>
				<td valign="top" nowrap>{$log.action}</td>
				<td valign="top">{$log.desc}</td>
			</tr>
{foreachelse}
			<tr><td colspan="6" class="emptylist">{_T("logs are empty")}</td></tr>
{/foreach}
		</table>
		<div class="infoline2 right">{_T("Pages:")}
			<span class="pagelink">
			{section name="pageLoop" start=1 loop=$nb_pages+1}
			{if $smarty.section.pageLoop.index eq $page}
			{$smarty.section.pageLoop.index}
			{else}
			<a href="log.php?page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
			{/if}
			{/section}
			</span>
		</div>
