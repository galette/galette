                <aside class="three wide computer only column">
					<div class="ui center aligned icon header">
						<img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="{$preferences->pref_nom}" class="icon"/>
                        <div class="content">
                            {$preferences->pref_nom}
                            <div class="sub header">{if $preferences->pref_slogan}{$preferences->pref_slogan}{/if}</div>
                        </div>
					</div>
{if $GALETTE_MODE eq 'DEMO'}
                    <div id="demo" title="{_T string="This application runs under DEMO mode, all features may not be available."}">
                        {_T string="Demonstration"}
                    </div>
{/if}
                    <div class="ui vertical accordion menu">
{if $login->isAdmin() or $login->isStaff() or $login->isGroupManager()}
{* Dirty trick to set active accordion fold using in_array tests on title and
content divs. Would be better to assign this array from model *}
{$management_routes = ['members', 'advanced-search', 'searches', 'groups', 'contribution', 'editmember', 'transaction', 'reminders', 'history', 'mailings', 'export', 'import', 'charts']}
                        <div class="item">
                            <div class="image header title{if $cur_route|in_array:$management_routes} active{/if}">
                                <i class="dharmachakra icon"></i>
                                {_T string="Management"}
                                <i class="dropdown icon"></i>
                            </div>
                            <div class="content{if $cur_route|in_array:$management_routes} active{/if}">
                                <a href="{path_for name="members"}" class="{if $cur_route eq "members"}active {/if}item" title="{_T string="View, search into and filter member's list"}">{_T string="List of members"}</a>
                                <a href="{path_for name="advanced-search"}" class="{if $cur_route eq "advanced-search"}active {/if}item" title="{_T string="Perform advanced search into members list"}">{_T string="Advanced search"}</a>
                                <a href="{path_for name="searches"}" class="{if $cur_route eq "searches"}active {/if}item" title="{_T string="Saved searches"}">{_T string="Saved searches"}</a>
                                <a href="{path_for name="groups"}" class="{if $cur_route eq "groups"}active {/if}item" title="{_T string="View and manage groups"}">{_T string="Manage groups"}</a>
    {if $login->isAdmin() or $login->isStaff()}
                                <a href="{path_for name="contributions" data=["type" => "contributions"]}" class="{if $cur_route eq "contributions" and $cur_subroute eq "contributions"}active {/if}item" title="{_T string="View and filter contributions"}">{_T string="List of contributions"}</a>
                                <a href="{path_for name="contributions" data=["type" => "transactions"]}" class="{if $cur_route eq "contributions" and $cur_subroute eq "transactions"}active {/if}item" title="{_T string="View and filter transactions"}">{_T string="List of transactions"}</a>
                                <a href="{path_for name="editmember" data=["action" => "add"]}" class="{if $cur_route eq "editmember"}active {/if}item" title="{_T string="Add new member in database"}">{_T string="Add a member"}</a>
                                <a href="{path_for name="contribution" data=["type" => "fee", "action" => "add"]}" class="{if $cur_route eq "contribution" and $cur_subroute eq "fee"}active {/if}item" title="{_T string="Add new membership fee in database"}">{_T string="Add a membership fee"}</a>
                                <a href="{path_for name="contribution" data=["type" => "donation", "action" => "add"]}" class="{if $cur_route eq "contribution" and $cur_subroute eq "donation"}active {/if}item" title="{_T string="Add new donation in database"}">{_T string="Add a donation"}</a>
                                <a href="{path_for name="transaction" data=["action" => "add"]}" class="{if $cur_route eq "transaction"}active {/if}item" title="{_T string="Add new transaction in database"}">{_T string="Add a transaction"}</a>
                                <a href="{path_for name="reminders"}" class="{if $cur_route eq "reminders"}active {/if}item" title="{_T string="Send reminders to late members"}">{_T string="Reminders"}</a>
                                <a href="{path_for name="history"}" class="{if $cur_route eq "history"}active {/if}item" title="{_T string="View application's logs"}">{_T string="Logs"}</a>
                                <a href="{path_for name="mailings"}" class="{if $cur_route eq "mailings"}active {/if}item" title="{_T string="Manage mailings that has been sent"}">{_T string="Manage mailings"}</a>
                                <a href="{path_for name="export"}" class="{if $cur_route eq "export"}active {/if}item" title="{_T string="Export some data in various formats"}">{_T string="Exports"}</a>
                                <a href="{path_for name="import"}" class="{if $cur_route eq "import" or $cur_route eq "importModel"}active {/if}item" title="{_T string="Import members from CSV files"}">{_T string="Imports"}</a>
                                <a href="{path_for name="charts"}" class="{if $cur_route eq "charts"}active {/if}item" title="{_T string="Various charts"}">{_T string="Charts"}</a>
    {/if}
                            </div>
                        </div>
{/if}
{if $preferences->showPublicPages($login) eq true}
{* Dirty trick to set active accordion fold using in_array tests on title and
content divs. Would be better to assign this array from model.
Need to find a way to let plugins declare their own routes *}
{$public_routes = ['publicList', 'map']}
                        <div class="item">
                            <div class="image header title{if $cur_route|in_array:$public_routes} active{/if}">
                                <i class="eye outline icon"></i>
                                {_T string="Public pages"}
                                <i class="dropdown icon"></i>
                            </div>
                            <div class="content{if $cur_route|in_array:$public_routes} active{/if}">
                            <a href="{path_for name="publicList" data=["type" => "list"]}" class="{if $cur_route eq "publicList" and $cur_subroute eq "list"}active {/if}item" title="{_T string="Members list"}">{_T string="Members list"}</a>
                            <a href="{path_for name="publicList" data=["type" => "trombi"]}" class="{if $cur_route eq "publicList" and $cur_subroute eq "trombi"}active {/if}item" title="{_T string="Trombinoscope"}">{_T string="Trombinoscope"}</a>
                            {* Include plugins menu entries *}
                            {$plugins->getPublicMenus($tpl)}
                            </div>
                        </div>
{/if}
                        {* Include plugins menu entries *}
                        {$plugins->getMenus($tpl)}
                    </div>
                </aside>
