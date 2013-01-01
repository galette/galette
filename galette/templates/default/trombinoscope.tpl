{foreach from=$members item=member}
        <div class="trombino">
            <img src="{$galette_base_path}picture.php?id_adh={$member->id}&amp;rand={$time}" height="{$member->picture->getOptimalHeight()}" width="{$member->picture->getOptimalWidth()}" alt="{$member->sfullname}{if $member->nickname ne ''} ({$member->nickname|htmlspecialchars}){/if}"/>
            <br/>{$member->sfullname}{if $member->nickname ne ''} ({$member->nickname|htmlspecialchars}){/if}
        </div>
{foreachelse}
        <div id="infobox">{_T string="No member to show"}</div>
{/foreach}