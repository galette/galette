		<H1 class="titre">{_T("Management of members")}</H1>
		<FORM action="gestion_adherents.php" method="get" name="filtre">
		<DIV id="listfilter">
			{_T("Search:")}&nbsp;
			<INPUT type="text" name="filtre_nom" value="{$smarty.session.filtre_adh_nom}">&nbsp;
		 	{_T("among:")}&nbsp;
			<SELECT name="filtre" onChange="form.submit()">
				{html_options options=$filtre_options selected=$smarty.session.filtre_adh}
			</SELECT>
			<SELECT name="filtre_2" onChange="form.submit()">
				{html_options options=$filtre_2_options selected=$smarty.session.filtre_adh_2}
			</SELECT>
			<INPUT type="submit" value="{_T("Filter")}">
		</DIV>
		<TABLE id="infoline" width="100%">
			<TR>
				<TD class="left">{$nb_members} {if $nb_members != 1}{_T("members")}{else}{_T("member")}{/if}</TD>
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
							<A href="gestion_adherents.php?nbshow={$smarty.get.nbshow}&page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</A>
						{/if}
					{/section}
					</SPAN>
				</TD>
			</TR>
		</TABLE>
		</FORM>
		<FORM action="gestion_adherents.php" method="post" name="listform">
		<TABLE width="100%" id="listing"> 
			<TR> 
				<TH width="15" class="listing">#</TH> 
	  			<TH width="250" class="listing left"> 
					<A href="gestion_adherents.php?tri=0" class="listing">{_T("Name")}</A>
					{if $smarty.session.tri_adh eq 0}
					{if $smarty.session.tri_adh_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt="">
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt="">
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt="">
					{/if}
				</TH>
				<TH class="listing left" nowrap>
					<A href="gestion_adherents.php?tri=1" class="listing">{_T("Nickname")}</A>
					{if $smarty.session.tri_adh eq 1}
					{if $smarty.session.tri_adh_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt="">
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt="">
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt="">
					{/if}
				</TH> 
				<TH class="listing left"> 
					<A href="gestion_adherents.php?tri=2" class="listing">{_T("Status")}</A>
					{if $smarty.session.tri_adh eq 2}
					{if $smarty.session.tri_adh_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt="">
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt="">
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt="">
					{/if}
				</TH> 
				<TH class="listing left"> 
					<A href="gestion_adherents.php?tri=3" class="listing">{_T("State of dues")}</A>
					{if $smarty.session.tri_adh eq 3}
					{if $smarty.session.tri_adh_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt="">
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt="">
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt="">
					{/if}
				</TH> 
				<TH width="55" class="listing">{_T("Actions")}</TH> 
			</TR>
{foreach from=$members item=member key=ordre}
			<TR>
				<TD width="15" class="{$member.class}">{$ordre}</TD>
				<TD class="{$member.class}" nowrap>
					<INPUT type="checkbox" name="member_sel[]" value="{$member.id_adh}">
				{if $member.genre eq 1}
					<IMG src="{$template_subdir}images/icon-male.png" Alt="{_T("[M]")}" align="middle" width="10" height="12">
				{elseif $member.genre eq 2 || $member.genre eq 3}
					<IMG src="{$template_subdir}images/icon-female.png" Alt="{_T("[W]")}" align="middle" width="10" height="12">
				{else}
					<IMG src="{$template_subdir}images/icon-empty.png" Alt="" align="middle" width="10" height="12">
				{/if}
				{if $member.email != ''}
					<A href="mailto:{$member.email}"><IMG src="{$template_subdir}images/icon-mail.png" Alt="{_T("[Mail]")}" align="middle" border="0" width="14" height="10"></A>
				{else}
					<IMG src="{$template_subdir}images/icon-empty.png" Alt="" align="middle" border="0" width="14" height="10">
				{/if}
				{if $member.admin eq 1}
					<IMG src="{$template_subdir}images/icon-star.png" Alt="{_T("[admin]")}" align="middle" width="12" height="13">
				{else}
					<IMG src="{$template_subdir}images/icon-empty.png" Alt="" align="middle" width="12" height="13">
				{/if}
				<A href="voir_adherent.php?id_adh={$member.id_adh}">{$member.nom} {$member.prenom}</A>
				</TD>
				<TD class="{$member.class}" nowrap>{$member.pseudo}</TD>
				<TD class="{$member.class}" nowrap>{$member.statut}</TD>
				<TD class="{$member.class}" nowrap>{$member.statut_cotis}</TD>
				<TD class="{$member.class}" center">
					<A href="ajouter_adherent.php?id_adh={$member.id_adh}"><IMG src="{$template_subdir}images/icon-edit.png" alt="{_T("[mod]")}" border="0" width="12" height="13"></A>
					<A href="gestion_contributions.php?id_adh={$member.id_adh}"><IMG src="{$template_subdir}images/icon-money.png" alt="{_T("[$]")}" border="0" width="13" height="13"></A>
					<A onClick="return confirm('{_T("Do you really want to delete this member from the base, this will delete also the history of her fees. To avoid this you can just unactivate her account.\n\nDo you still want to delete this member ?")|escape:"javascript"}')" href="gestion_adherents.php?sup={$member.id_adh}"><IMG src="{$template_subdir}images/icon-trash.png" alt="{_T("[del]")}" border="0" width="11" height="13"></A>
				</TD>
			</TR>
{foreachelse}
			<TR><TD colspan="6" class="emptylist">{_T("no member")}</TD></TR>
{/foreach}
		</TABLE>
{if $nb_members != 0}		
		{literal}
		<SCRIPT LANGUAGE="JavaScript">
		<!--
		var checked = 1; 	
		function check()
		{
			for (var i=0;i<document.listform.elements.length;i++)
			{
				var e = document.listform.elements[i];
				if(e.type == "checkbox")
				{
					e.checked = checked;
				}
			}
			checked = !checked;
		}
		-->
		</SCRIPT>
		{/literal}
{/if}
		<TABLE id="infoline" width="100%">
			<TR>
{if $nb_members != 0}
				<TD class="left">
					<A href="#" onClick="check()">{_T("(Un)Check all")}</A><BR>
					{_T("Selection:")}
					<UL>
						<LI><INPUT type="submit" onClick="return confirm('{_T("Do you really want to delere all selected accounts (and related contributions)?")|escape:"javascript"}');" name="delete" value="{_T("Delete")}"></LI>
						<LI><INPUT type="submit" name="mailing" value="{_T("Mail all")}"></LI>
						<LI><INPUT type="submit" name="labels" value="{_T("Generate labels")}"></LI>
					</UL>
				</TD>
{/if}
				<TD class="right">{_T("Pages:")}
					<SPAN class="pagelink">
					{section name="pageLoop" start=1 loop=$nb_pages+1}
						{if $smarty.section.pageLoop.index eq $page}
							{$smarty.section.pageLoop.index}
						{else}
							<A href="gestion_adherents.php?nbshow={$smarty.get.nbshow}&page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</A>
						{/if}
					{/section}
					</SPAN>
				</TD>
			</TR>
		</TABLE>
		</FORM>
