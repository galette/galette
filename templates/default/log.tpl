		<H1 class="titre">{_T("Logs")}</H1>
		<FORM action="log.php" method="post">
			<DIV align="center"><INPUT type="submit" value="{_T("Flush the logs")}"/></DIV>
			<INPUT type="hidden" name="reset" value="1"/>
		</FORM>
		<TABLE class="infoline" width="100%" border="0">
			<TR>
				<TD class="left">{$nb_lines} {if $nb_lines != 1}{_T("lines")}{else}{_T("line")}{/if}</TD>
				<TD class="right">
					{_T("Pages:")}
					<SPAN class="pagelink">
{section name="pageLoop" start=1 loop=$nb_pages+1}
{if $smarty.section.pageLoop.index eq $page}
					{$smarty.section.pageLoop.index}
{else}
					<A href="log.php?page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</A>
{/if}
{/section}
					</SPAN>
				</TD>
			</TR>
		</TABLE>
		<TABLE width="100%" border="0"> 
			<TR>
				<TH width="15" class="listing">#</TH> 
				<TH class="listing left" width="150">
					<A href="log.php?tri=0" class="listing">{_T("Date")}</A>
					{if $smarty.session.tri_log eq 0}
					{if $smarty.session.tri_log_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</TH>
				<TH class="listing left" width="150">
					<A href="log.php?tri=1" class="listing">{_T("IP")}</A>
					{if $smarty.session.tri_log eq 1}
					{if $smarty.session.tri_log_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</TH>
				<TH class="listing left" width="150">
					<A href="log.php?tri=2" class="listing">{_T("User")}</A>
					{if $smarty.session.tri_log eq 2}
					{if $smarty.session.tri_log_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</TH>
				<TH class="listing left" width="150">
					<A href="log.php?tri=4" class="listing">{_T("Action")}</A>
					{if $smarty.session.tri_log eq 4}
					{if $smarty.session.tri_log_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</TH>
				<TH class="listing left">
					<A href="log.php?tri=3" class="listing">{_T("Description")}</A>
					{if $smarty.session.tri_log eq 3}
					{if $smarty.session.tri_log_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</TH>
			</TR>
{foreach from=$logs item=log key=ordre}
			<TR class="cotis-never">
				<TD width="15" valign="top">{$ordre}</TD>
				<TD valign="top" nowrap>{$log.date}</TD>
				<TD valign="top" nowrap>{$log.ip}</TD>
				<TD valign="top" nowrap>{$log.adh}</TD>
				<TD valign="top" nowrap>{$log.action}</TD>
				<TD valign="top">{$log.desc}</TD>
			</TR>
{foreachelse}
			<TR><TD colspan="6" class="emptylist">{_T("logs are empty")}</TD></TR>
{/foreach}
		</TABLE>
		<DIV class="infoline2 right">{_T("Pages:")}
			<SPAN class="pagelink">
			{section name="pageLoop" start=1 loop=$nb_pages+1}
			{if $smarty.section.pageLoop.index eq $page}
			{$smarty.section.pageLoop.index}
			{else}
			<A href="log.php?page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</A>
			{/if}
			{/section}
			</SPAN>
		</DIV>
