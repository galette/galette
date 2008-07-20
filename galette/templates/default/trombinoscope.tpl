		<h1 id="titre">{_T string="Trombinoscope"}</h1>
{foreach from=$members item=member key=ordre}
		<div class="trombino">
			<img src="../photos/{$member.id_adh}.{$member.pic_format}" height="{$member.pic_height}" width="{$member.pic_width}" alt="{$member.nom} {$member.prenom}{if $member.pseudo ne ''} ({$member.pseudo}){/if}"/>
			<br/>{$member.nom} {$member.prenom}{if $member.pseudo ne ''} ({$member.pseudo}){/if}
		</div>
{foreachelse}
		<div id="infobox">{_T string="No member to show"}</div>
{/foreach}