		<H1 class="titre">{_T("Management of transactions")}</H1>
		<FORM action="gestion_transactions.php" method="get" name="filtre">
		<TABLE class="infoline" width="100%">
			<TR>
				<TD class="left">{$nb_transactions} {if $nb_transactions > 1}{_T("transactions")}{else}{_T("transaction")}{/if}</TD>
				<TD class="center">
					{_T("Show:")}
					<SELECT name="nbshow" onChange="form.submit()">
						{html_options options=$nbshow_options selected=$numrows}
					</SELECT>
				</TD>
				<TD class="right">{_T("Pages:")}
					<SPAN class="pagelink">
					{section name="pageLoop" start=1 loop=$nb_pages+1}
						{if $smarty.section.pageLoop.index eq $page}
							{$smarty.section.pageLoop.index}
						{else}
							<A href="gestion_transactions.php?nbshow={$smarty.get.nbshow}&page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</A>
						{/if}
					{/section}
					</SPAN>
				</TD>
			</TR>
		</TABLE>
		</FORM>
		<TABLE width="100%">
			<TR>
				<TH width="15" class="listing">#</TH>
				<TH class="listing left">
					<A href="gestion_transactions.php?tri=0" class="listing">{_T("Date")}</A>
					{if $smarty.session.sort_by eq 0}
					{if $smarty.session.sort_direction eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</TH>
				<TH class="listing left">{_T("Description")}</TH>
{if $smarty.session.admin_status eq 1}
				<TH class="listing left">
					<A href="gestion_transactions.php?tri=1" class="listing">{_T("Originator")}</A>
					{if $smarty.session.sort_by eq 1}
					{if $smarty.session.sort_direction eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</TH>
{/if}
				<TH class="listing left">
					<A href="gestion_transactions.php?tri=2" class="listing">{_T("Amount")}</A>
					{if $smarty.session.sort_by eq 3}
					{if $smarty.session.sort_direction eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt=""/>
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt=""/>
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt=""/>
					{/if}
				</TH>
{if $smarty.session.admin_status eq 1}
				<TH width="55" class="listing">{_T("Actions")}</TH>
{/if}
			</TR>
{foreach from=$transactions item=transaction}
			<TR>
				<TD width="15" class="cotis-ok center" nowrap>{$transaction.trans_id}</TD>
				<TD width="50" class="cotis-ok" nowrap>
					{$transaction.trans_date}
				</TD>
				<TD class="cotis-ok" nowrap>
					{$transaction.trans_desc}
				</TD>
{if $smarty.session.admin_status eq 1}
			<TD class="cotis-ok" nowrap>
{if $smarty.session.id_adh eq ""}
				<A href="gestion_transactions.php?id_adh={$transaction.id_adh}">
					{$transaction.lastname} {$transaction.firstname}
				</A>
{else}
				<A href="voir_adherent.php?id_adh={$transaction.id_adh}">
					{$transaction.lastname} {$transaction.firstname}
				</A>
{/if}
			</TD>
{/if}
			<TD class="cotis-ok" nowrap>{$transaction.trans_amount}</TD>
{if $smarty.session.admin_status eq 1}
			<TD width="55" class="cotis-ok center" nowrap>
				<A href="ajouter_transaction.php?trans_id={$transaction.trans_id}"><IMG src="{$template_subdir}images/icon-edit.png" alt="{_T("[mod]")}" border="0" width="12" height="13"/></A>
				<A onClick="return confirm('{_T("Do you really want to delete this transaction of the database ?")|escape:"javascript"}')" href="gestion_transactions.php?sup={$transaction.trans_id}"><IMG src="{$template_subdir}images/icon-trash.png" alt="{_T("[del]")}" border="0" width="11" height="13"/></A>
			</TD>
{/if}
{foreachelse}
{if $smarty.session.admin_status eq 1}
			<TR><TD colspan="6" class="emptylist">{_T("no transaction")}</TD></TR>
{else}
			<TR><TD colspan="4" class="emptylist">{_T("no transaction")}</TD></TR>
{/if}
{/foreach}
		</TABLE>
		<DIV class="infoline2 right">
			{_T("Pages:")}
			<SPAN class="pagelink">
			{section name="pageLoop" start=1 loop=$nb_pages+1}
			{if $smarty.section.pageLoop.index eq $page}
			{$smarty.section.pageLoop.index}
			{else}
			<A href="gestion_transactions.php?nbshow={$smarty.get.nbshow}&page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</A>
			{/if}
			{/section}
			</SPAN>
		</DIV>
