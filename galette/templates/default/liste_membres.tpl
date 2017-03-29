{extends file="public_page.tpl"}
{block name="content"}
{if $members|@count > 0}
        <p>{_T string="This page shows only members who have choosen to be visible on the public lists and are up-to-date within their contributions. If you want your account to be visible here, edit your profile and check 'Be visible in the members list'"}</p>
        <form action="{path_for name="filterPublicMemberslist"}" method="POST" id="filtre">
        <table class="infoline">
            <tr>
                <td class="left">{$nb_members} {if $nb_members != 1}{_T string="members"}{else}{_T string="member"}{/if}</td>
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
        <table class="listing">
            <thead>

                <tr>
                    <th class="left">
                        <a href="{path_for name="publicMembers" data=["option" => {_T string="order" domain="routes"}, "value" => {Galette\Repository\Members::ORDERBY_NAME}]}" class="listing">
                            {_T string="Name"}
                            {if $filters->orderby eq constant('Galette\Repository\Members::ORDERBY_NAME')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="left">
                        <a href="{path_for name="publicMembers" data=["option" => {_T string="order" domain="routes"}, "value" => {Galette\Repository\Members::ORDERBY_NICKNAME}]}" class="listing">
                            {_T string="Nickname"}
                            {if $filters->orderby eq constant('Galette\Repository\Members::ORDERBY_NICKNAME')}
                                {if $filters->ordered eq constant('Galette\Filters\MembersList::ORDER_ASC')}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt=""/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt=""/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    {if $login->isLogged()}
                    <th class="left">
                        {_T string="Email"}
                    </th>
                    {/if}
                    <th class="left">
                        {_T string="Informations"}
                    </th>
                </tr>
            </thead>
            <tbody>
    {foreach from=$members item=member name=allmembers}
                <tr class="{if $smarty.foreach.allmembers.iteration % 2 eq 0}even{else}odd{/if}">
                    <td class="{$member->getRowClass(true)} nowrap username_row" data-scope="row">
                    {if $member->isCompany()}
                        <img src="{$template_subdir}images/icon-company.png" alt="" width="16" height="16"/>
                    {elseif $member->isMan()}
                        <img src="{$template_subdir}images/icon-male.png" alt="" width="16" height="16"/>
                    {elseif $member->isWoman()}
                        <img src="{$template_subdir}images/icon-female.png" alt="" width="16" height="16"/>
                    {else}
                        <img src="{$template_subdir}images/icon-empty.png" alt="" width="10" height="12"/>
                    {/if}

                    {if $member->website ne ''}
                        <a href="{$member->website}">{$member->sfullname}</a>
                    {else}
                        {$member->sfullname}
                    {/if}
                    </td>
                    <td class="{$member->getRowClass(true)} nowrap" data-title="{_T string="Nickname"}">{$member->nickname|htmlspecialchars}</td>
                    {if $login->isLogged()}
                    <td class="{$member->getRowClass(true)} nowrap" data-title="{_T string="Email"}"><a href="mailto:{$member->email}">{$member->email}</a></td>
                    {/if}
                    <td class="{$member->getRowClass(true)} nowrap" data-title="{_T string="Informations"}">{$member->others_infos}</td>
                </tr>
    {/foreach}
            </tbody>
        </table>
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
