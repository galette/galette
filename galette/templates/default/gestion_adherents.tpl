		<form action="gestion_adherents.php" method="get" id="filtre">
		<h1 id="titre">{_T string="Management of members"}</h1>
{if $error_detected|@count != 0}
		<div id="errorbox">
			<h1>{_T string="- ERROR -"}</h1>
			<ul>
{foreach from=$error_detected item=error}
				<li>{$error}</li>
{/foreach}
			</ul>
		</div>
{/if}
		<div id="listfilter">
			<label for="filtre_nom">{_T string="Search:"}&nbsp;</label>
			<input type="text" name="filtre_nom" id="filtre_nom" value="{$smarty.session.filtre_adh_nom}"/>&nbsp;
		 	{_T string="in:"}&nbsp;
			<select name="filtre_fld">
				{html_options options=$filtre_fld_options selected=$smarty.session.filtre_adh_fld}
			</select>
		 	{_T string="among:"}&nbsp;
			<select name="filtre" onchange="form.submit()">
				{html_options options=$filtre_options selected=$smarty.session.filtre_adh}
			</select>
			<select name="filtre_2" onchange="form.submit()">
				{html_options options=$filtre_2_options selected=$smarty.session.filtre_adh_2}
			</select>
			<input type="submit" class="submit inline" value="{_T string="Filter"}"/>
		</div>
		<table class="infoline">
			<tr>
				<td class="left">{$nb_members} {if $nb_members != 1}{_T string="members"}{else}{_T string="member"}{/if}</td>
				<td class="center">
					<label for="nbshow">{_T string="Show:"}</label>
					<select name="nbshow" id="nbshow">
						{html_options options=$nbshow_options selected=$numrows}
					</select>
					<noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
				</td>
				<td class="right">{_T string="Pages:"}
					<span class="pagelink">
					{section name="pageLoop" start=1 loop=$nb_pages+1}
						{if $smarty.section.pageLoop.index eq $page}
							{$smarty.section.pageLoop.index}
						{else}
							<a href="gestion_adherents.php?nbshow={$smarty.get.nbshow}&amp;page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
						{/if}
					{/section}
					</span>
				</td>
			</tr>
		</table>
		</form>
		<form action="gestion_adherents.php" method="post" id="listform">
		<table id="listing">
			<thead>
				<tr> 
					<th class="listing" id="id_row">#</th>
					<th class="listing left"> 
						<a href="gestion_adherents.php?tri=0" class="listing">
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
						<a href="gestion_adherents.php?tri=1" class="listing">
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
						<a href="gestion_adherents.php?tri=2" class="listing">
							{_T string="Status"}
							{if $smarty.session.tri_adh eq 2}
							{if $smarty.session.tri_adh_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>
					</th> 
					<th class="listing left"> 
						<a href="gestion_adherents.php?tri=3" class="listing">
							{_T string="State of dues"}
							{if $smarty.session.tri_adh eq 3}
							{if $smarty.session.tri_adh_sens eq 0}
							<img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
							{else}
							<img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
							{/if}
							{/if}
						</a>
					</th> 
					<th class="listing">{_T string="Actions"}</th> 
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="6" class="right">
						<a href="#" onclick="check();" class="fleft">{_T string="(Un)Check all"}</a>
						{_T string="Pages:"}
						<span class="pagelink">
						{section name="pageLoop" start=1 loop=$nb_pages+1}
							{if $smarty.section.pageLoop.index eq $page}
								{$smarty.section.pageLoop.index}
							{else}
								<a href="gestion_adherents.php?nbshow={$smarty.get.nbshow}&amp;page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</a>
							{/if}
						{/section}
						</span>
					</td>
				</tr>
			</tfoot>
			<tbody>
{foreach from=$members item=member key=ordre}
				<tr>
					<td class="{$member.class} right">{$ordre}</td>
					<td class="{$member.class} nowrap username_row">
						<input type="checkbox" name="member_sel[]" value="{$member.id_adh}"/>
					{if $member.genre eq 1}
						<img src="{$template_subdir}images/icon-male.png" alt="{_T string="[M]"}" width="16" height="16"/>
					{elseif $member.genre eq 2 || $member.genre eq 3}
						<img src="{$template_subdir}images/icon-female.png" alt="{_T string="[W]"}" width="16" height="16"/>
					{elseif $member.genre eq 4}
						<img src="{$template_subdir}images/icon-company.png" alt="{_T string="[W]"}" width="16" height="16"/>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="10" height="12"/>
					{/if}
					{if $member.email != ''}
						<a href="mailto:{$member.email}"><img src="{$template_subdir}images/icon-mail.png" alt="{_T string="[Mail]"}" width="16" height="16"/></a>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="14" height="10"/>
					{/if}
					{if $member.admin eq 1}
						<img src="{$template_subdir}images/icon-star.png" alt="{_T string="[admin]"}" width="16" height="16"/>
					{else}
						<img src="{$template_subdir}images/icon-empty.png" alt="" width="12" height="13"/>
					{/if}
					<a href="voir_adherent.php?id_adh={$member.id_adh}">{$member.nom} {$member.prenom}</a>
					</td>
					<td class="{$member.class} nowrap">{$member.pseudo|htmlspecialchars}</td>
					<td class="{$member.class} nowrap">{$member.statut}</td>
					<td class="{$member.class} nowrap">{$member.statut_cotis}</td>
					<td class="{$member.class} center nowrap actions_row">
						<a href="subscription_form.php?id_adh={$member.id_adh}"><img src="{$template_subdir}images/icon-fiche.png" alt="Fiche adhérent" width="18" height="13"/></a>
						<a href="ajouter_adherent.php?id_adh={$member.id_adh}"><img src="{$template_subdir}images/icon-edit.png" alt="{_T string="[mod]"}" width="16" height="16"/></a>
						<a href="gestion_contributions.php?id_adh={$member.id_adh}"><img src="{$template_subdir}images/icon-money.png" alt="{_T string="[$]"}" width="16" height="16"/></a>
						<a onclick="return confirm('{_T string="Do you really want to delete this member from the base? This will also delete the history of his fees. You could instead disable the account.\\n\\nDo you still want to delete this member ?"|escape:"javascript"}')" href="gestion_adherents.php?sup={$member.id_adh}"><img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16"/></a>
					</td>
				</tr>
{foreachelse}
				<tr><td colspan="6" class="emptylist">{_T string="no member"}</td></tr>
{/foreach}
			</tbody>
		</table>
{if $nb_members != 0}		
		{literal}
		<script type="text/javascript">
		//<![CDATA[ 
		var checked = 1; 	
		function check()
		{
			for (var i=0;i<document.forms.listform.elements.length;i++)
			{
				var e = document.forms.listform.elements[i];
				if(e.type == "checkbox")
				{
					e.checked = checked;
				}
			}
			checked = !checked;
			return(false);
		}
		//]]>
		</script>
		{/literal}
{/if}
{if $nb_members != 0}
			<ul class="selection_menu">
				<li>{_T string="Selection:"}</li>
				<li><input type="submit" id="delete" class="submit" onclick="return confirm('{_T string="Do you really want to delete all selected accounts (and related contributions)?"|escape:"javascript"}');" name="delete" value="{_T string="Delete"}"/></li>
				<li><input type="submit" id="sendmail" class="submit" name="mailing" value="{_T string="Mail"}"/></li>
				<li><input type="submit" class="submit" name="labels" value="{_T string="Generate labels"}"/></li>
				<li><input type="submit" class="submit" name="cards" value="{_T string="Generate Member Cards"}"/></li>
			</ul>
{/if}
		</form>
		{literal}
		<script type="text/javascript">
			//<![CDATA[
				$('#nbshow').change(function() {
					this.form.submit();
				});
			//]]>
		</script>
		{/literal}