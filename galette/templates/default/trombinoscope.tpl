{extends file="public_page.tpl"}

{block name="content"}
{if $members|@count > 0}
        <div class="ui icon yellow small message">
            <i class="info circle icon"></i>
            <div class="content">
                <p>{_T string="This page shows only members who have choosen to be visible on the public lists and are up-to-date within their contributions. If you want your account to be visible here, edit your profile and check 'Be visible in the members list'"}</p>
            </div>
        </div>
        <form action="{path_for name="filterPublicList" data=["type" => "trombi"]}" method="POST" id="filtre" class="ui form">
            <div class="infoline">
                <div class="ui basic horizontal segments">
                    <div class="ui basic fitted segment">
                        <div class="ui label">{$nb_members} {if $nb_members != 1}{_T string="members"}{else}{_T string="member"}{/if}</div>
                    </div>
                    <div class="ui basic right aligned fitted segment">
                        <div class="inline field">
                            <label for="nbshow">{_T string="Records per page:"}</label>
                            <select name="nbshow" id="nbshow" class="ui dropdown">
                                {html_options options=$nbshow_options selected=$numrows}
                            </select>
                            <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div class="ui doubling four column grid">
    {foreach from=$members item=member}
            <div class="column">
                <div class="ui fluid card">
                    {assign var="mid" value=$member->id}
                    <div class="image">
                        <img src="{path_for name="photo" data=["id" => $mid, "rand" => $time]}" height="{$member->picture->getOptimalHeight()}" width="{$member->picture->getOptimalWidth()}" alt="{$member->sfullname}{if $member->nickname ne ''} ({$member->nickname|htmlspecialchars}){/if}"/>
                    </div>
                    <div class="content">
                        <div class="header">{$member->sfullname}</div>
                        {if $member->nickname ne ''}<div class="meta">{$member->nickname|htmlspecialchars}</div>{/if}
                    </div>
                </div>
            </div>
    {/foreach}
        </div>
        <div class="ui basic center aligned fitted segment">
            <div class="ui pagination menu">
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

{block name="javascripts"}
    {if $members|@count > 0}
        <script type="text/javascript">
            $(function(){
                $('#nbshow').change(function() {
                    this.form.submit();
                });
            });
        </script>
    {/if}
{/block}
