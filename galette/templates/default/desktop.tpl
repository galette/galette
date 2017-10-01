{extends file="page.tpl"}
{block name="content"}
    {if not $hide_telemetry}
        <section id="share">
            <header class="ui-state-default ui-state-active">
                {_T string="Help us know about you!"}
            </header>
            <div>
                <p>
                    {_T string="Take a moment to share some informations with us so we can know better Galette's uses."}<br/>
                </p>
        {if not $telemetry_sent}
                    <a id="telemetry" href="#" title="{_T string="Send anonymous and imprecise data about your Galette instance"}">{_T string="Telemetry"}</a>
        {/if}
        {if not $registered}
                    <a id="register" href="{$smarty.const.GALETTE_TELEMETRY_URI}reference?showmodal&uuid={$reguuid}" title="{_T string="Register your organization as a Galette user"}" target="_blank">{_T string="Register"}</a>
        {/if}
            <p class="center" title="{_T string="The panel will be automatically hidden once you have registered and send telemetry data. Check the box if you want to hide it anyways."}">
                <input type="checkbox" name="hide_telemetry" id="hide_telemetry" value="1"{if $hide_telemetry} checked="checked"{/if}/>
                <label for="hide_telemetry">{_T string="Hide this panel"}</label>
            </p>
        </section>
    {/if}
        <section id="desktop">
            <header class="ui-state-default ui-state-active">
                {_T string="Activities"}
            </header>
            <div>
                <a id="members" href="{path_for name="members"}" title="{_T string="View, search into and filter member's list"}">{_T string="Members"}</a>
                <a id="groups" href="{path_for name="groups"}" title="{_T string="View and manage groups"}">{_T string="Groups"}</a>
{if $login->isAdmin() or $login->isStaff()}
                <a id="contribs" href="{path_for name="contributions" data=["type" => {_T string="contributions" domain="routes"}]}" title="{_T string="View and filter contributions"}">{_T string="Contributions"}</a><br/>
                <a id="transactions" href="{path_for name="contributions" data=["type" => {_T string="transactions" domain="routes"}]}" title="{_T string="View and filter transactions"}">{_T string="Transactions"}</a>
                <a id="mailings" href="{path_for name="mailings"}" title="{_T string="Manage mailings that has been sent"}">{_T string="Mailings"}</a>
                <a id="reminder" href="{path_for name="reminders"}" title="{_T string="Send reminders to late members"}">{_T string="Reminders"}</a>
{/if}
{if $login->isAdmin()}
                <a id="prefs" href="{path_for name="preferences"}" title="{_T string="Set applications preferences (address, website, member's cards configuration, ...)"}">{_T string="Settings"}</a>
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

    {if not $hide_telemetry and not $telemetry_sent}
        {include file="telemetry.tpl" part="dialog"}
    {/if}
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
                        var _url = '{path_for name="members"}';
                        window.location.replace(_url);
                    }
                });

    {if not $hide_telemetry}
                $('#hide_telemetry').change(function(){
                    var _checked = $(this).is(':checked');
                    $.cookie(
                        'hide_galette_telemetry',
                        (_checked ? 1 : 0),
                        { expires: 365 }
                    );
                    var _url = '{path_for name="dashboard"}';
                    window.location.replace(_url);
                });

        {if not $telemetry_sent}
            {include file="telemetry.tpl" part="jsdialog" orig="desktop"}
        {/if}
        {if not $registered}
            {include file="telemetry.tpl" part="jsregister" orig="desktop"}
        {/if}
    {/if}
            });
        </script>
{/block}
