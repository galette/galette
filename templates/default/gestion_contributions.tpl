		<H1 class="titre">{_T("Management of contributions")}</H1>
		<DIV id="listfilter">
			<FORM action="gestion_contributions.php" method="get" name="filtre">
				{_T("Show contributions since")}&nbsp;
				<INPUT type="text" name="contrib_filter_1" maxlength="10" size="10" value="{$smarty.session.filtre_date_cotis_1}">
				{_T("until")}&nbsp;
				<INPUT type="text" name="contrib_filter_2" maxlength="10" size="10" value="{$smarty.session.filtre_date_cotis_2}">
				<INPUT type="submit" value="{_T("Filter")}">
			</FORM>
		</DIV>
		<TABLE id="infoline" width="100%">
			<TR>
				<TD class="left">{$nb_contributions} {if $nb_contributions != 1}{_T("contributions")}{else}{_T("contribution")}{/if}</TD>
				<TD class="right">{_T("Pages:")}
					<SPAN class="pagelink">
					{section name="pageLoop" start=1 loop=$nb_pages+1}
						{if $smarty.section.pageLoop.index eq $page}
							{$smarty.section.pageLoop.index}
						{else}
							<A href="gestion_contributions.php?page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</A>
						{/if}
					{/section}
					</SPAN>
				</TD>
			</TR>
		</TABLE>
		<TABLE width="100%"> 
			<TR> 
				<TH width="15" class="listing">#</TH> 
				<TH class="listing left"> 
					<A href="gestion_contributions.php?tri=0" class="listing">{_T("Date")}</A>
					{if $smarty.session.tri_cotis eq 0}
					{if $smarty.session.tri_cotis_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt="">
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt="">
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt="">
					{/if}
				</TH> 
{if $smarty.session.admin_status eq 1}
				<TH class="listing left"> 
					<A href="gestion_contributions.php?tri=1" class="listing">{_T("Member")}</A>
					{if $smarty.session.tri_cotis eq 1}
					{if $smarty.session.tri_cotis_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt="">
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt="">
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt="">
					{/if}
				</TH> 
{/if}
				<TH class="listing left"> 
					<A href="gestion_contributions.php?tri=2" class="listing">{_T("Type")}</A>
					{if $smarty.session.tri_cotis eq 2}
					{if $smarty.session.tri_cotis_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt="">
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt="">
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt="">
					{/if}
				</TH> 
				<TH class="listing left"> 
					<A href="gestion_contributions.php?tri=3" class="listing">{_T("Amount")}</A>
					{if $smarty.session.tri_cotis eq 3}
					{if $smarty.session.tri_cotis_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt="">
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt="">
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt="">
					{/if}
				</TH> 
				<TH class="listing left"> 
					<A href="gestion_contributions.php?tri=4" class="listing">{_T("Duration")}</A>
					{if $smarty.session.tri_cotis eq 4}
					{if $smarty.session.tri_cotis_sens eq 0}
					<IMG src="{$template_subdir}images/asc.png" width="7" height="7" alt="">
					{else}
					<IMG src="{$template_subdir}images/desc.png" width="7" height="7" alt="">
					{/if}
					{else}
					<IMG src="{$template_subdir}images/icon-empty.png" width="7" height="7" alt="">
					{/if}
				</TH> 
{if $smarty.session.admin_status eq 1}
				<TH width="55" class="listing">{_T("Actions")}</TH>
{/if}
			</TR> 
{foreach from=$contributions item=contribution key=ordre}
			<TR> 
				<TD width="15" class="{$contribution.class} center" nowrap>{$ordre}</TD> 
				<TD width="50" class="{$contribution.class}" nowrap>
					{$contribution.date}
			</TD> 
{if $smarty.session.admin_status eq 1}
			<TD class="{$contribution.class}" nowrap>
{if $smarty.session.filtre_cotis_adh eq ""}
				<A href="gestion_contributions.php?id_adh={$contribution.id_adh}">
					{$contribution.nom} {$contribution.prenom}
				</A>
{else}
				<A href="voir_adherent.php?id_adh={$contribution.id_adh}">
					{$contribution.nom} {$contribution.prenom}
				</A>
{/if}
			</TD> 
{/if}
			<TD class="{$contribution.class}" nowrap>{$contribution.libelle_type_cotis}</TD> 
			<TD class="{$contribution.class}" nowrap>{$contribution.montant_cotis}</TD> 
			<TD class="{$contribution.class}" nowrap>{$contribution.duree_mois_cotis}</TD> 
{if $smarty.session.admin_status eq 1}
			<TD width="55" class="{$contribution.class} center" nowrap>  
				<A href="ajouter_contribution.php?id_cotis={$contribution.id_cotis}"><IMG src="{$template_subdir}images/icon-edit.png" alt="{_T("[mod]")}" border="0" width="12" height="13"></A>
				<A onClick="return confirm('{_T("Do you really want to delete this contribution of the database ?")|escape:"javascript"}')" href="gestion_contributions.php?sup={$contribution.id_cotis}"><IMG src="{$template_subdir}images/icon-trash.png" alt="{_T("[del]")}" border="0" width="11" height="13"></A>
			</TD> 
{/if}
{foreachelse}			
{if $smarty.session.admin_status eq 1}
			<TR><TD colspan="7" class="emptylist">{_T("no contribution")}</TD></TR>
{else}
			<TR><TD colspan="5" class="emptylist">{_T("no contribution")}</TD></TR>
{/if}
{/foreach}			
		</TABLE>
		<DIV id="infoline2" class="right">
			{_T("Pages:")}
			<SPAN class="pagelink">
			{section name="pageLoop" start=1 loop=$nb_pages+1}
			{if $smarty.section.pageLoop.index eq $page}
			{$smarty.section.pageLoop.index}
			{else}
			<A href="gestion_contributions.php?page={$smarty.section.pageLoop.index}">{$smarty.section.pageLoop.index}</A>
			{/if}
			{/section}
			</SPAN>
		</DIV>
{if $smarty.session.filtre_cotis_adh != ""}
{*
<?	
	// affichage du temps d'ahésion restant si on est en train de visualiser
	// les cotisations d'un membre unique
	
	if ($_SESSION["filtre_cotis_adh"]!="")
	{
		$requete = "SELECT date_echeance, bool_exempt_adh
			    FROM ".PREFIX_DB."adherents
			    WHERE id_adh='".$_SESSION["filtre_cotis_adh"]."'";
		$resultat = $DB->Execute($requete);
		
		// temps d'adhésion
		if($resultat->fields[1])
		{
			$statut_cotis = _T("Freed of dues");
			$color = "#DDFFDD";
		}
		else
		{
			if ($resultat->fields[0]=="")
			{
				$statut_cotis = _T("Never contributed");
				$color = "#EEEEEE";			
			}
			else
			{
			
			
			$date_fin = split("-",$resultat->fields[0]);
			$ts_date_fin = mktime(0,0,0,$date_fin[1],$date_fin[2],$date_fin[0]);
			$aujourdhui = time();
			
			$difference = intval(($ts_date_fin - $aujourdhui)/(3600*24));
			if ($difference==0)
			{
				$statut_cotis = _T("Last day!");
				$color = "#FFDDDD";
			}
			elseif ($difference<0)
			{
				$statut_cotis = _T("Late of")." ".-$difference." "._T("days")." ("._T("since")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
				$color = "#FFDDDD";
			}
			else
			{
				if ($difference!=1)
					$statut_cotis = $difference." "._T("days remaining")." ("._T("ending on")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
				else
					$statut_cotis = $difference." "._T("day remaining")." ("._T("ending on")." ".$date_fin[2]."/".$date_fin[1]."/".$date_fin[0].")";
				if ($difference < 30)
					$color = "#FFE9AB";
				else
					$color = "#DDFFDD";	
			}
			
			}
		}		
		
		
		/*$days_left = get_days_left($DB, $_SESSION["filtre_cotis_adh"]);
		$cumul = $days_left["cumul"];
		$statut_cotis = $days_left["text"];
		$color = $days_left["color"];*/
?>	
		<BR>
		<DIV align="center">
		  <TABLE bgcolor="<? echo $color; ?>">
		    <TR>
		      <TD><? echo $statut_cotis; ?></TD>
		    </TR>
		  </TABLE>
		  
*}
		<BR>
		<DIV align="center">
{if $smarty.session.admin_status eq 1}
		<BR>
			<A href="voir_adherent.php?id_adh={$smarty.session.filtre_cotis_adh}">{_T("[ See member profile ]")}</A>
			&nbsp;&nbsp;&nbsp;
			<A href="ajouter_contribution.php?id_adh={$smarty.session.filtre_cotis_adh}">{_T("[ Add a contribution ]")}</A>
		</DIV>
{/if}
{/if}
