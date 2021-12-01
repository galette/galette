{extends file="page.tpl"}
{block name="content"}
    <div class="container">
        <div>
{if not $hide_telemetry and $GALETTE_MODE neq 'DEMO'}
    <div class="ui info message">
        <i class="close icon"></i>
        <div class="header">
            {_T string="Help us know about you!"}
        </div>
        <p>
            {* TODO: use a trash icon next to close one instead of a checkbox *}
            {_T string="Take a moment to share some information with us so we can know better Galette's uses."}
            <span class="ui checkbox" title="{_T string="The panel will be automatically hidden once you have registered and send telemetry data. Check the box if you want to hide it anyways."}">
                <input type="checkbox" name="hide_telemetry" id="hide_telemetry" value="1"{if $hide_telemetry} checked="checked"{/if}/>
                <label for="hide_telemetry">{_T string="Hide this panel"}</label>
            </span>
        </p>
        <div class="ui two centered stackable cards">
            {if not $telemetry_sent}
                <a id="telemetry" class="ui card" href="#" title="{_T string="Send anonymous and imprecise data about your Galette instance"}">
                    <div class="content">
                        <div class="center aligned header">
                            <i class="ui huge chart bar grey icon"></i>
                            {_T string="Telemetry"}
                        </div>
                    </div>
                </a>

            {/if}
            {if not $registered}
                <a class="ui card" href="{$smarty.const.GALETTE_TELEMETRY_URI}reference?showmodal&uuid={$reguuid}" title="{_T string="Register your organization as a Galette user"}" target="_blank">
                    <div class="content">
                        <div class="center aligned header">
                            <i class="ui huge id card outline grey icon"></i>
                            {_T string="Register"}
                        </div>
                    </div>
                </a>
            {/if}
        </div>
    </div>
{/if}
            <h3 class="ui top attached header">{_T string="Activities"}</h3>
            <div class="ui attached segment">
                <div class="ui three stackable cards">
{if $login->isAdmin() or $login->isStaff() or $login->isGroupManager()}
                    <a class="ui card" href="{path_for name="members"}" title="{_T string="View, search into and filter member's list"}">
                        <div class="content">
                            <div class="header">
                                <i class="ui huge user alternate grey icon"></i>
                                {_T string="Members"}
                            </div>
                        </div>
                    </a>
                    <a class="ui card" href="{path_for name="groups"}" title="{_T string="View and manage groups"}">
                        <div class="content">
                            <div class="header">
                                <i class="ui huge users grey icon"></i>
                                {_T string="Groups"}
                            </div>
                        </div>
                    </a>
    {if $login->isAdmin() or $login->isStaff()}
                    <a class="ui card" href="{path_for name="contributions" data=["type" => "contributions"]}" title="{_T string="View and filter contributions"}">
                        <div class="content">
                            <div class="header">
                                <i class="ui huge book grey icon"></i>
                                {_T string="Contributions"}
                            </div>
                        </div>
                    </a>
                    <a class="ui card" href="{path_for name="contributions" data=["type" => "transactions"]}" title="{_T string="View and filter transactions"}">
                        <div class="content">
                            <div class="header">
                                <i class="ui huge columns grey icon"></i>
                                {_T string="Transactions"}
                            </div>
                        </div>
                    </a>
                    <a class="ui card" href="{path_for name="mailings"}" title="{_T string="Manage mailings that has been sent"}">
                        <div class="content">
                            <div class="header">
                                <i class="ui huge mail bulk grey icon"></i>
                                {_T string="Mailings"}
                            </div>
                        </div>
                    </a>
                    <a class="ui card"" href="{path_for name="reminders"}" title="{_T string="Send reminders to late members"}">
                        <div class="content">
                            <div class="header">
                                <i class="ui huge bell grey icon"></i>
                                {_T string="Reminders"}
                            </div>
                        </div>
                    </a>
    {/if}
    {if $login->isAdmin()}
                    <a class="ui card" href="{path_for name="preferences"}" title="{_T string="Set applications preferences (address, website, member's cards configuration, ...)"}">
                        <div class="content">
                            <div class="header">
                                <i class="ui huge tools grey icon"></i>
                                {_T string="Settings"}
                            </div>
                        </div>
                    </a>
                    <a class="ui card" href="{path_for name="plugins"}" title="{_T string="Information about available plugins"}">
                        <div class="content">
                            <div class="header">
                                <i class="ui huge puzzle piece grey icon"></i>
                                {_T string="Plugins"}
                            </div>
                        </div>
                    </a>
                    {* Include plugins user dashboard *}
                    {$plugins->getDashboard($tpl)}
    {/if}
{else}
                    {* Single member *}
                    <a class="ui card" href="{path_for name="me"}" title="{_T string="View my member card"}">
                        <div class="content">
                            <div class="header">
                                <i class="ui huge user alternate grey icon"></i>
                                {_T string="My information"}
                            </div>
                        </div>
                    </a>
                    <a class="ui card" href="{path_for name="contributions" data=["type" => "contributions"]}" title="{_T string="View and filter all my contributions"}">
                        <div class="content">
                            <div class="header">
                                <i class="ui huge receipt grey icon"></i>
                                {_T string="Contributions"}
                            </div>
                        </div>
                    </a>
                    <a class="ui card" href="{path_for name="contributions" data=["type" => "transactions"]}" title="{_T string="View and filter all my transactions"}">
                        <div class="content">
                            <div class="header">
                                <i class="ui huge book grey icon"></i>
                                {_T string="transactions"}
                            </div>
                        </div>
                    </a>
                    {* Include plugins user dashboard *}
                    {$plugins->getMemberDashboard($tpl)}
{/if}
                </div>
            </div>
            <div class="ui basic segment">
                <div class="ui toggle checkbox">
                    <input type="checkbox" name="show_dashboard" id="show_dashboard" value="1"{if $show_dashboard} checked="checked"{/if}/>
                    <label for="show_dashboard">{_T string="Show dashboard on login"}</label>
                </div>
            </div>
        </div>
{if $news|@count > 0}
        <div>
            <h3 class="ui top attached header">{_T string="News"}</h3>
            <div class="ui attached segment">
                <div class="ui bulleted list">
    {foreach from=$news item=post}
                    <div class="item">
                        <a href="{$post.url}" target="_blank">{$post.title}</a>
                    </div>
    {/foreach}
                </div>
            </div>
        </div>
{/if}
    </div>

{if not $hide_telemetry and not $telemetry_sent}
    {include file="telemetry.tpl" part="dialog"}
{/if}
{/block}

{block name="javascripts"}
    <script>
        $(function() {
            $('#show_dashboard').change(function(){
                var _checked = $(this).is(':checked');
                Cookies.set(
                    'show_galette_dashboard',
                    (_checked ? 1 : 0),
                        {
                            expires: 365,
                            path: '/'
                        }
                );
                if ( !_checked ) {
                    var _url = '{path_for name="members"}';
                    window.location.replace(_url);
                }
            });

{if not $hide_telemetry}
            $('#hide_telemetry').change(function(){
                var _checked = $(this).is(':checked');
                Cookies.set(
                    'hide_galette_telemetry',
                    (_checked ? 1 : 0),
                        {
                            expires: 365,
                            path: '/'
                        }
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
