        <section id="desktop">
            <header class="ui-state-default ui-state-active">
                {_T string="Activities"}
            </header>
            <div>
                <a id="members" href="gestion_adherents.php" title="{_T string="View, search into and filter member's list"}">{_T string="Members"}</a>
                <a id="groups" href="gestion_groupes.php" title="{_T string="View and manage groups"}">{_T string="Groups"}</a>
{if $login->isAdmin() or $login->isStaff()}
                <a id="contribs" href="gestion_contributions.php?id_adh=all" title="{_T string="View and filter contributions"}">{_T string="Contributions"}</a><br/>
                <a id="transactions" href="gestion_transactions.php" title="{_T string="View and filter transactions"}">{_T string="Transactions"}</a>
                <a id="mailings" href="{$galette_base_path}gestion_mailings.php" title="{_T string="Manage mailings that has been sent"}">{_T string="Mailings"}</a>
{/if}
{if $login->isAdmin()}
                <a id="prefs" href="{$galette_base_path}preferences.php" title="{_T string="Set applications preferences (adress, website, member's cards configuration, ...)"}">{_T string="Settings"}</a><br/>
                <a id="plugins" href="{$galette_base_path}plugins.php" title="{_T string="Informations about available plugins"}">{_T string="Plugins"}</a>
{/if}
            </div>
        </section>
        <aside id="news">
            <header class="ui-state-default ui-state-active">
                {_T string="News"}
            </header>
            <div id="news-tabs">
                <ul>
                    <li><a href="#twitter">Twitter</a></li>
                    <li><a href="#googleplus">Google+</a></li>
                </ul>
{if $tweets|@count == 0}
                <p id="twitter">{_T string="No tweets has been retrieved."}</p>
{else}
                <ul id="twitter">
    {foreach item=tweet from=$tweets}
                    <li>{$tweet}</li>
    {/foreach}
                </ul>
{/if}
{if $gplus|@count == 0}
                <p id="googleplus">{_T string="No Google+ posts has been retrieved."}</p>
{else}
                <ul id="googleplus">
    {foreach item=gp key=url from=$gplus}
                    <li>{$gp} <a href="{$url}" class="googleplus_plus button small" title="{_T string="Read complete post"}">...</a></li>
    {/foreach}
                </ul>
{/if}
            </div>
        </aside>
        <p class="center">
            <input type="checkbox" name="show_dashboard" id="show_dashboard" value="1"{if $show_dashboard} checked="checked"{/if}/>
            <label for="show_dashboard">{_T string="Show dashboard on login"}</label>
        </p>
        <script>
            $(function() {ldelim}
                $( "#news-tabs" ).tabs();
                $('#twitter a, #googleplus a').click(function(){ldelim}
                    window.open(this.href);
                    return false;
                {rdelim});

                $('#show_dashboard').change(function(){ldelim}
                    var _checked = $(this).is(':checked');
                    $.cookie(
                        'show_galette_dashboard',
                        (_checked ? 1 : 0),
                        {ldelim} expires: 365 {rdelim}
                    );
                    if ( !_checked ) {ldelim}
                        var _url = window.location.href;
                        window.location.replace(
                            _url.replace(
                                /\/desktop\.php.*/,
                                '/gestion_adherents.php'
                            )
                        );
                    {rdelim}
                {rdelim});
            {rdelim});
        </script>