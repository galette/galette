                <aside class="three wide computer only column">
                    <div id="menu">
{*if $login->isSuperAdmin() or $is_public}
                        <div id="superadmin" title="{_T string="You are actually logged-in as superadmin. Some functionnalities may not be available since this is *not* a regular member."}">
                            {_T string="Superadmin"}
                        </div>
{/if*}
{if $GALETTE_MODE eq 'DEMO'}
                        <div id="demo" title="{_T string="This application runs under DEMO mode, all features may not be available."}">
                            {_T string="Demonstration"}
                        </div>
{/if}
{if $login->isAdmin() or $login->isStaff() or $login->isGroupManager()}
                        <h1 class="nojs">{_T string="Management"}</h1>
                        <ul>
                            <li{if $cur_route eq "members"} class="selected"{/if}><a href="{path_for name="members"}" title="{_T string="View, search into and filter member's list"}">{_T string="List of members"}</a></li>
                            <li{if $cur_route eq "advanced-search"} class="selected"{/if}><a href="{path_for name="advanced-search"}" title="{_T string="Perform advanced search into members list"}">{_T string="Advanced search"}</a></li>
                            <li{if $cur_route eq "searches"} class="selected"{/if}><a href="{path_for name="searches"}" title="{_T string="Saved searches"}">{_T string="Saved searches"}</a></li>
                            <li{if $cur_route eq "groups"} class="selected"{/if}><a href="{path_for name="groups"}" title="{_T string="View and manage groups"}">{_T string="Manage groups"}</a></li>
  {/if}
  {if $login->isAdmin() or $login->isStaff()}
                            <li{if $cur_route eq "contributions" and $cur_subroute eq "contributions"} class="selected"{/if}><a href="{path_for name="contributions" data=["type" => "contributions"]}" title="{_T string="View and filter contributions"}">{_T string="List of contributions"}</a></li>
                            <li{if $cur_route eq "contributions" and $cur_subroute eq "transactions"} class="selected"{/if}><a href="{path_for name="contributions" data=["type" => "transactions"]}" title="{_T string="View and filter transactions"}">{_T string="List of transactions"}</a></li>
                            <li{if $cur_route eq "editmember"} class="selected"{/if}><a href="{path_for name="editmember" data=["action" => "add"]}" title="{_T string="Add new member in database"}">{_T string="Add a member"}</a></li>
                            <li{if $cur_route eq "contribution" and $cur_subroute eq "fee"} class="selected"{/if}><a href="{path_for name="contribution" data=["type" => "fee", "action" => "add"]}" title="{_T string="Add new membership fee in database"}">{_T string="Add a membership fee"}</a></li>
                            <li{if $cur_route eq "contribution" and $cur_subroute eq "donation"} class="selected"{/if}><a href="{path_for name="contribution" data=["type" => "donation", "action" => "add"]}" title="{_T string="Add new donation in database"}">{_T string="Add a donation"}</a></li>
                            <li{if $cur_route eq "transaction"} class="selected"{/if}><a href="{path_for name="transaction" data=["action" => "add"]}" title="{_T string="Add new transaction in database"}">{_T string="Add a transaction"}</a></li>
                            <li{if $cur_route eq "reminders"} class="selected"{/if}><a href="{path_for name="reminders"}" title="{_T string="Send reminders to late members"}">{_T string="Reminders"}</a></li>
                            <li{if $cur_route eq "history"} class="selected"{/if}><a href="{path_for name="history"}" title="{_T string="View application's logs"}">{_T string="Logs"}</a></li>
                            <li{if $cur_route eq "mailings"} class="selected"{/if}><a href="{path_for name="mailings"}" title="{_T string="Manage mailings that has been sent"}">{_T string="Manage mailings"}</a></li>
                            <li{if $cur_route eq "export"} class="selected"{/if}><a href="{path_for name="export"}" title="{_T string="Export some data in various formats"}">{_T string="Exports"}</a></li>
                            <li{if $cur_route eq "import" or $cur_route eq "importModel"} class="selected"{/if}><a href="{path_for name="import"}" title="{_T string="Import members from CSV files"}">{_T string="Imports"}</a></li>
                            <li class="mnu_last{if $cur_route eq "charts"} selected{/if}"><a href="{path_for name="charts"}" title="{_T string="Various charts"}">{_T string="Charts"}</a></li>
                        </ul>
{/if}
{if $preferences->showPublicPages($login) eq true}
                        <h1 class="nojs">{_T string="Public pages"}</h1>
                        <ul>
                            <li><a href="{path_for name="publicList" data=["type" => "list"]}" title="{_T string="Members list"}">{_T string="Members list"}</a></li>
                            <li><a href="{path_for name="publicList" data=["type" => "trombi"]}" title="{_T string="Trombinoscope"}">{_T string="Trombinoscope"}</a></li>
                            {* Include plugins menu entries *}
                            {$plugins->getPublicMenus($tpl)}
                        </ul>
{/if}
{*if $login->isAdmin()}
                        <h1 class="nojs">{_T string="Configuration"}</h1>
                        <ul>
                            <li{if $cur_route eq "preferences"} class="selected"{/if}><a href="{path_for name="preferences"}" title="{_T string="Set applications preferences (address, website, member's cards configuration, ...)"}">{_T string="Settings"}</a></li>
                            <li{if $cur_route eq "plugins"} class="selected"{/if}><a href="{path_for name="plugins"}" title="{_T string="Informations about available plugins"}">{_T string="Plugins"}</a></li>
                            <li{if $cur_route eq "configureCoreFields"} class="selected"{/if}><a href="{path_for name="configureCoreFields"}" title="{_T string="Customize fields order, set which are required, and for who they're visibles"}">{_T string="Core fields"}</a></li>
                            <li{if $cur_route eq "configureDynamicFields" or $cur_route eq 'editDynamicField'} class="selected"{/if}><a href="{path_for name="configureDynamicFields"}" title="{_T string="Manage additional fields for various forms"}">{_T string="Dynamic fields"}</a></li>
                            <li{if $cur_route eq "dynamicTranslations"} class="selected"{/if}><a href="{path_for name="dynamicTranslations"}" title="{_T string="Translate additionnals fields labels"}">{_T string="Translate labels"}</a></li>
                            <li{if $cur_route eq "entitleds" and $cur_subroute eq "status"} class="selected"{/if}><a href="{path_for name="entitleds" data=["class" => "status"]}" title="{_T string="Manage statuses"}">{_T string="Manage statuses"}</a></li>
                            <li{if $cur_route eq "entitleds" and $cur_subroute eq "contributions-types"} class="selected"{/if}><a href="{path_for name="entitleds" data=["class" => "contributions-types"]}" title="{_T string="Manage contributions types"}">{_T string="Contributions types"}</a></li>
                            <li{if $cur_route eq "texts"} class="selected"{/if}><a href="{path_for name="texts"}" title="{_T string="Manage emails texts and subjects"}">{_T string="Emails content"}</a></li>
                            <li{if $cur_route eq "titles"} class="selected"{/if}><a href="{path_for name="titles"}" title="{_T string="Manage titles"}">{_T string="Titles"}</a></li>
                            <li{if $cur_route eq "pdfModels"} class="selected"{/if}><a href="{path_for name="pdfModels"}" title="{_T string="Manage PDF models"}">{_T string="PDF models"}</a></li>
                            <li{if $cur_route eq "paymentTypes"} class="selected"{/if}><a href="{path_for name="paymentTypes"}" title="{_T string="Manage payment types"}">{_T string="Payment types"}</a></li>
                            <li><a href="{path_for name="emptyAdhesionForm"}" title="{_T string="Download empty adhesion form"}">{_T string="Empty adhesion form"}</a></li>
            {if $login->isSuperAdmin()}
                            <li{if $cur_route eq "fakeData"} class="selected"{/if}><a href="{path_for name="fakeData"}">{_T string="Generate fake data"}</a></li>
                            <li{if $cur_route eq "adminTools"} class="selected"{/if}><a href="{path_for name="adminTools"}" title="{_T string="Various administrative tools"}">{_T string="Admin tools"}</a></li>
            {/if}
                        </ul>
{/if*}
                        {* Include plugins menu entries *}
                        {$plugins->getMenus($tpl)}
                    </div>
                </aside>
