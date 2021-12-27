{extends file="public_page.tpl"}

{block name="content"}
{if $members|@count > 0}
        <div class="ui icon yellow small message">
            <i class="info circle icon"></i>
            <div class="content">
                {_T string="This page shows only members who have choosen to be visible on the public lists and are up-to-date within their contributions. If you want your account to be visible here, edit your profile and check 'Be visible in the members list'"}
            </div>
        </div>
        <form action="{path_for name="filterPublicList" data=["type" => "trombi"]}" method="POST" id="filtre" class="ui form">
            <div class="infoline">
                <div class="ui basic horizontal segments">
                    <div class="ui basic fitted segment">
                        <div class="ui label">{_T string="%count member" plural="%count members" count=$nb_members pattern="/%count/" replace=$nb_members}</div>
                    </div>
                    <div class="ui basic right aligned fitted segment">
                        <div class="inline field">
                            <label for="nbshow">{_T string="Records per page:"}</label>
                            <select name="nbshow" id="nbshow" class="ui dropdown nochosen">
                                {html_options options=$nbshow_options selected=$numrows}
                            </select>
                            <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
                            {include file="forms_types/csrf.tpl"}
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div class="ui doubling six column cards">
    {foreach from=$members item=member}
            <div class="ui fluid card">
                {assign var="mid" value=$member->id}
                <div class="image">
                    <img src="{path_for name="photo" data=["id" => $mid]}" height="{$member->picture->getOptimalHeight()}" width="{$member->picture->getOptimalWidth()}" alt="{$member->sfullname}{if $member->nickname ne ''} ({$member->nickname|htmlspecialchars}){/if}"/>
                </div>
                <div class="content">
                    <div class="header">{$member->sfullname}</div>
                    {if $member->nickname ne ''}<div class="meta">{$member->nickname|htmlspecialchars}</div>{/if}
                </div>
            </div>
    {/foreach}
        </div>
        <div class="ui basic center aligned fitted segment">
            <div class="ui inverted pagination menu">
                <div class="header item">
                    {_T string="Pages:"}
                </div>
                {$pagination}
            </div>
        </div>
{else}
    <div class="ui icon info small message">
        <i class="info icon"></i>
        <div class="content">
            <div class="header">
                {_T string="No member to show"}
            </div>
        </div>
    </div>
{/if}
{/block}
