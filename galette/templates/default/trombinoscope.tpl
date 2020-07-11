{extends file="public_page.tpl"}

{block name="content"}
{if $members|@count > 0}
<form action="{path_for name="filterPublicList" data=["type" => "trombi"]}" method="POST" id="filtre">
    <table class="infoline">
        <tr>
            <td class="left">{_T string="%count member" plural="%count members" count=$nb_members pattern="/%count/" replace=$nb_members}</td>
            <td class="right">
                <label for="nbshow">{_T string="Records per page:"}</label>
                <select name="nbshow" id="nbshow">
                    {html_options options=$nbshow_options selected=$numrows}
                </select>
                <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
            </td>
        </tr>
    </table>
</form>
    {foreach from=$members item=member}
        <div class="trombino">
            {assign var="mid" value=$member->id}
            <img src="{path_for name="photo" data=["id" => $mid]}" height="{$member->picture->getOptimalHeight()}" width="{$member->picture->getOptimalWidth()}" alt="{$member->sfullname}{if $member->nickname ne ''} ({$member->nickname|htmlspecialchars}){/if}"/>
            <br/>{$member->sfullname}{if $member->nickname ne ''} ({$member->nickname|htmlspecialchars}){/if}
        </div>
    {/foreach}

<div class="center cright">
    {_T string="Pages:"}<br/>
    <ul class="pages">{$pagination}</ul>
</div>
{else}
        <div id="infobox">{_T string="No member to show"}</div>
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
