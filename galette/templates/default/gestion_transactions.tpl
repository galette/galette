		<h1 id="titre">{_T("Management of transactions")}</h1>
		<form action="gestion_transactions.php" method="get" id="filtre">
		<table class="infoline" width="100%">
			<tr>
				<td class="left">{$nb_transactions} {if $nb_transactions > 1}{_T("transactions")}{else}{_T("transaction")}{/if}</td>
				<td class="center">
					<label for="nbshow">{_T("Show:")}</label>
					<select name="nbshow" id="nbshow">
						{html_options options=$nbshow_options selected=$numrows}
					</select>
				</td>
				<td class="right">{_T("Pages:")}
					<span class="pagelink">
					{section name="pageLoop" start=1 loop=$nb_pages+1}
						{if $smarty.section.pageLoop.index eq $page}
							{$smarty.section.pageLoop.index}
						{else}
							<a href="gestion_transactions.php?nbshow={$smarty.get.nbshow}&page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
						{/if}
					{/section}
					</span>
				</td>
			</tr>
		</table>
		</form>
		<table width="100%">
			<tr>
				<th class="listing" id="id_row">#</th>
				<th class="listing left date_row">
					<a href="gestion_transactions.php?tri=0" class="listing">{_T("Date")}
					{if $smarty.session.sort_by eq 0}
					{if $smarty.session.sort_direction eq 0}
					<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
					{else}
					<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
					{/if}
					{/if}
					</a>
				</th>
				<th class="listing left">{_T("Description")}</th>
{if $smarty.session.admin_status eq 1}
				<th class="listing left">
					<a href="gestion_transactions.php?tri=1" class="listing">{_T("Originator")}
					{if $smarty.session.sort_by eq 1}
					{if $smarty.session.sort_direction eq 0}
					<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
					{else}
					<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
					{/if}
					{/if}
					</a>
				</th>
{/if}
				<th class="listing left">
					<a href="gestion_transactions.php?tri=2" class="listing">{_T("Amount")}
					{if $smarty.session.sort_by eq 3}
					{if $smarty.session.sort_direction eq 0}
					<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
					{else}
					<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
					{/if}
					{/if}
					</a>
				</th>
{if $smarty.session.admin_status eq 1}
				<th class="listing actions_row">{_T("Actions")}</th>
{/if}
			</tr>
{foreach from=$transactions item=transaction}
			<tr>
				<td class="cotis-ok center nowrap">{$transaction.trans_id}</td>
				<td class="cotis-ok nowrap">{$transaction.trans_date}</td>
				<td class="cotis-ok nowrap">{$transaction.trans_desc}</td>
{if $smarty.session.admin_status eq 1}
				<td class="cotis-ok">
{if $smarty.session.id_adh eq ""}
					<a href="gestion_transactions.php?id_adh={$transaction.id_adh}">
						{$transaction.lastname} {$transaction.firstname}
					</a>
{else}
					<a href="voir_adherent.php?id_adh={$transaction.id_adh}">
						{$transaction.lastname} {$transaction.firstname}
					</a>
{/if}
				</td>
{/if}
				<td class="cotis-ok nowrap">{$transaction.trans_amount}</td>
{if $smarty.session.admin_status eq 1}
				<td class="cotis-ok center nowrap">
					<a href="ajouter_transaction.php?trans_id={$transaction.trans_id}"><img src="{$template_subdir}images/icon-edit.png" alt="{_T("[mod]")}" width="16" height="16"/></a>
					<a onclick="return confirm('{_T("Do you really want to delete this transaction of the database ?")|escape:"javascript"}')" href="gestion_transactions.php?sup={$transaction.trans_id}"><img src="{$template_subdir}images/icon-trash.png" alt="{_T("[del]")}" width="16" height="16"/></a>
				</td>
{/if}
			</tr>
{foreachelse}
{if $smarty.session.admin_status eq 1}
			<tr><td colspan="6" class="emptylist">{_T("no transaction")}</td></tr>
{else}
			<tr><td colspan="4" class="emptylist">{_T("no transaction")}</td></tr>
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
			<a href="gestion_transactions.php?nbshow={$smarty.get.nbshow}&page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
			{/if}
			{/section}
			</span>
		</div>
		{literal}
		<script type="text/javascript">
			<![CDATA[
				$('#nbshow').change(function() {
					this.form.submit();
				});
			]]>
		</script>
		{/literal}