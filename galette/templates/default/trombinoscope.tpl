{extends file="public_page.tpl"}
{block name="content"}
{foreach from=$members item=member}
        <div class="trombino">
            {assign var="mid" value=$member->id}
            <img src="{path_for name="photo" data=["id" => $mid, "rand" => $time]}" height="{$member->picture->getOptimalHeight()}" width="{$member->picture->getOptimalWidth()}" alt="{$member->sfullname}{if $member->nickname ne ''} ({$member->nickname|htmlspecialchars}){/if}"/>
            <br/>{$member->sfullname}{if $member->nickname ne ''} ({$member->nickname|htmlspecialchars}){/if}
        </div>
{foreachelse}
        <div id="infobox">{_T string="No member to show"}</div>
{/foreach}
{/block}
