{extends file="page.tpl"}
{block name="content"}
        <section id="desktop">
            <header class="ui-state-default ui-state-active">
                {_T string="Activities"}
            </header>
            <div>
                <a id="members" href="{path_for name="members"}" title="{_T string="View, search into and filter member's list"}">{_T string="Members"}</a>
                <a id="groups" href="{path_for name="groups"}" title="{_T string="View and manage groups"}">{_T string="Groups"}</a>
{if $login->isAdmin() or $login->isStaff()}
                <a id="contribs" href="{path_for name="contributions" data=["type" => "contributions"]}" title="{_T string="View and filter contributions"}">{_T string="Contributions"}</a><br/>
                <a id="transactions" href="{path_for name="contributions" data=["type" => "transactions"]}" title="{_T string="View and filter transactions"}">{_T string="Transactions"}</a>
                <a id="mailings" href="{path_for name="mailings"}" title="{_T string="Manage mailings that has been sent"}">{_T string="Mailings"}</a>
                <a id="reminder" href="{path_for name="reminders"}" title="{_T string="Send reminders to late members"}">{_T string="Reminders"}</a>
{/if}
{if $login->isAdmin()}
                <a id="prefs" href="{path_for name="preferences"}" title="{_T string="Set applications preferences (adress, website, member's cards configuration, ...)"}">{_T string="Settings"}</a>
                <a id="plugins" href="{path_for name="plugins"}" title="{_T string="Informations about available plugins"}">{_T string="Plugins"}</a>
{/if}
            </div>
        </section>
{if $news|@count > 0}
    <section id="news">
        <header class="ui-state-default ui-state-active">
            {_T string="News"}
        </header>
        <div>
    {foreach from=$news item=post}
            <p>
                <a href="{$post.url}" target="_blank">{$post.title}</a>
            </p>
    {/foreach}
        </div>
    </section>
{/if}
        <p class="center">
            <input type="checkbox" name="show_dashboard" id="show_dashboard" value="1"{if $show_dashboard} checked="checked"{/if}/>
            <label for="show_dashboard">{_T string="Show dashboard on login"}</label>
        </p>
{/block}

{block name="javascripts"}
        <script>
            $(function() {
                $('#show_dashboard').change(function(){
                    var _checked = $(this).is(':checked');
                    $.cookie(
                        'show_galette_dashboard',
                        (_checked ? 1 : 0),
                        { expires: 365 }
                    );
                    if ( !_checked ) {
                        var _url = window.location.href;
                        window.location.replace(
                            _url.replace(
                                /\/desktop\.php.*/,
                                '/gestion_adherents.php'
                            )
                        );
                    }
                });
            });
        </script>
{/block}
