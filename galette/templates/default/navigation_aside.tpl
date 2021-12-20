                <aside class="ui computer only toc">
                    <div class="ui basic center aligned segment">
                        <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="{$preferences->pref_nom}" class="icon"/>
                        <div class="ui header">
                            {$preferences->pref_nom}
                            {if $preferences->pref_slogan}<div class="sub tiny header">{$preferences->pref_slogan}</div>{/if}
                        </div>
                    </div>
{if $login->isSuperAdmin()}
                    <div class="ui small red inverted center aligned segment" title="{_T string="You are actually logged-in as superadmin. Some functionnalities may not be available since this is *not* a regular member."}">
                        <i class="ui user shield icon"></i>
                        <strong>{_T string="Superadmin"}</strong>
                    </div>
{/if}
{if $GALETTE_MODE eq 'DEMO'}
                    <div class="ui small orange inverted center aligned segment" title="{_T string="This application runs under DEMO mode, all features may not be available."}">
                        <strong>{_T string="Demonstration"}</strong>
                    </div>
{/if}
                    <div class="ui vertical accordion menu">
{if $login->isLogged()}
                        <a href="{path_for name="dashboard"}"
                            title="{_T string="Go to Galette's dashboard"}"
                            class="{if $cur_route eq "dashboard"}active {/if}item"
                        >
                            <div class="image header title">
                                <i class="compass icon" aria-hidden="true"></i>
                                {_T string="Dashboard"}
                            </div>
                        </a>
{/if}

{if !$login->isSuperAdmin()}
    {* Dirty trick to set active accordion fold using in_array tests on title and
       content divs. Would be better to assign this array from model *}
    {$my_routes = ['me', 'addMemberChild']}
                        <div class="item">
                            <div class="image header title{if $cur_route|in_array:$my_routes} active{/if}">
                                <i class="user icon"></i>
                                {_T string="My Account"}
                                <i class="dropdown icon"></i>
                            </div>
                            <div class="content{if $cur_route|in_array:$my_routes} active{/if}">
                                <a
                                    href="{path_for name="contributions" data=["type" => "contributions"]}"
                                    class="{if $cur_route eq "contributions" and $cur_subroute eq "contributions"}active {/if}item"
                                    title="{_T string="View and filter all my contributions"}"
                                >{_T string="My contributions"}</a>
                                <a
                                    href="{path_for name="contributions" data=["type" => "transactions"]}"
                                    class="{if $cur_route eq "contributions" and $cur_subroute eq "transactions"}active {/if}item"
                                    title="{_T string="View and filter all my transactions"}"
                                >{_T string="My transactions"}</a>
                                <a
                                    href="{path_for name="me"}"
                                    class="{if $cur_route eq "me" or $cur_route eq "member"}active {/if}item"
                                    title="{_T string="View my member card"}"
                                >{_T string="My information"}</a>
    {if $preferences->pref_bool_create_member and !$login->isSuperAdmin()}
                                <a
                                    href="{path_for name="addMemberChild"}"
                                    class="{if $cur_route eq "addMemberChild"}active {/if}item"
                                    title="{_T string="Add new child member in database"}"
                                >{_T string="Add a child member"}</a>
    {/if}

                            </div>
                        </div>
{/if}

{* Dirty trick to set active accordion fold using in_array tests on title and
   content divs. Would be better to assign this array from model *}
{$members_routes = ['members', 'member', 'advanced-search', 'searches', 'editMember', 'addMember']}
                        <div class="item">
                            <div class="image header title{if $cur_route|in_array:$members_routes} active{/if}">
                                <i class="users icon"></i>
                                {_T string="Members"}
                                <i class="dropdown icon"></i>
                            </div>
                            <div class="content{if $cur_route|in_array:$members_routes} active{/if}">
                                {if $login->isAdmin() or $login->isStaff() or $login->isGroupManager()}
                                    <a href="{path_for name="members"}" class="{if $cur_route eq "members" or $cur_route eq "member"}active {/if}item" title="{_T string="View, search into and filter member's list"}">{_T string="List of members"}</a>
                                    <a href="{path_for name="advanced-search"}" class="{if $cur_route eq "advanced-search"}active {/if}item" title="{_T string="Perform advanced search into members list"}">{_T string="Advanced search"}</a>
                                    <a href="{path_for name="searches"}" class="{if $cur_route eq "searches"}active {/if}item" title="{_T string="Saved searches"}">{_T string="Saved searches"}</a>
                                {/if}
                                {if $login->isAdmin() or $login->isStaff() or ($login->isGroupManager() and $preferences->pref_bool_groupsmanagers_create_member)}
                                    <a href="{path_for name="addMember"}" class="{if $cur_route eq "editMember" or $cur_route eq "addMember"}active {/if}item" title="{_T string="Add new member in database"}">{_T string="Add a member"}</a>
                                {/if}
                            </div>
                        </div>

{* Dirty trick to set active accordion fold using in_array tests on title and
   content divs. Would be better to assign this array from model *}
{$contributions_routes = ['contributions', 'addContribution', 'addTransaction', 'reminders']}
                        <div class="item">
                            <div class="image header title{if $cur_route|in_array:$contributions_routes} active{/if}">
                                <i class="cookie icon"></i>
                                {_T string="Contributions"}
                                <i class="dropdown icon"></i>
                            </div>
                            <div class="content{if $cur_route|in_array:$contributions_routes} active{/if}">
                                {if $login->isAdmin() or $login->isStaff()}
                                    <a href="{path_for name="contributions" data=["type" => "contributions"]}" class="{if $cur_route eq "contributions" and $cur_subroute eq "contributions"}active {/if}item" title="{_T string="View and filter contributions"}">{_T string="List of contributions"}</a>
                                    <a href="{path_for name="contributions" data=["type" => "transactions"]}" class="{if $cur_route eq "contributions" and $cur_subroute eq "transactions"}active {/if}item" title="{_T string="View and filter transactions"}">{_T string="List of transactions"}</a>
                                    <a href="{path_for name="addContribution" data=["type" => "fee"]}" class="{if $cur_route eq "addContribution" and $cur_subroute eq "fee"}active {/if}item" title="{_T string="Add new membership fee in database"}">{_T string="Add a membership fee"}</a>
                                    <a href="{path_for name="addContribution" data=["type" => "donation", "action" => "add"]}" class="{if $cur_route eq "addContribution" and $cur_subroute eq "donation"}active {/if}item" title="{_T string="Add new donation in database"}">{_T string="Add a donation"}</a>
                                    <a href="{path_for name="addTransaction"}" title="{_T string="Add new transaction in database"}" class="{if $cur_route eq "addTransaction" or $cur_route eq "editTransaction"}active {/if}item">{_T string="Add a transaction"}</a>
                                    <a href="{path_for name="reminders"}" class="{if $cur_route eq "reminders"}active {/if}item" title="{_T string="Send reminders to late members"}">{_T string="Reminders"}</a>
                                {/if}
                            </div>
                        </div>

{* Dirty trick to set active accordion fold using in_array tests on title and
   content divs. Would be better to assign this array from model *}
{$management_routes = ['groups', 'history', 'mailings', 'export', 'import', 'charts']}
                        <div class="item">
                            <div class="image header title{if $cur_route|in_array:$management_routes} active{/if}">
                                <i class="dharmachakra icon"></i>
                                {_T string="Management"}
                                <i class="dropdown icon"></i>
                            </div>
                            <div class="content{if $cur_route|in_array:$management_routes} active{/if}">
                                {if $login->isAdmin() or $login->isStaff() or $login->isGroupManager()}
                                    <a href="{path_for name="groups"}" class="{if $cur_route eq "groups"}active {/if}item" title="{_T string="View and manage groups"}">{_T string="Manage groups"}</a>
                                {/if}
                                {if $login->isAdmin() or $login->isStaff()}
                                    <a href="{path_for name="history"}" class="{if $cur_route eq "history"}active {/if}item" title="{_T string="View application's logs"}">{_T string="Logs"}</a>
                                    <a href="{path_for name="mailings"}" class="{if $cur_route eq "mailings" or $cur_route eq "mailing"}active {/if}item" title="{_T string="Manage mailings that has been sent"}">{_T string="Manage mailings"}</a>
                                    <a href="{path_for name="export"}" class="{if $cur_route eq "export"}active {/if}item" title="{_T string="Export some data in various formats"}">{_T string="Exports"}</a>
                                    <a href="{path_for name="import"}" class="{if $cur_route eq "import" or $cur_route eq "importModel"}active {/if}item" title="{_T string="Import members from CSV files"}">{_T string="Imports"}</a>
                                    <a href="{path_for name="charts"}" class="{if $cur_route eq "charts"}active {/if}item" title="{_T string="Various charts"}">{_T string="Charts"}</a>
                                {/if}
                            </div>
                        </div>

{if $preferences->showPublicPages($login) eq true}
{* Dirty trick to set active accordion fold using in_array tests on title and
content divs. Would be better to assign this array from model.
Need to find a way to let plugins declare their own routes *}
{$public_routes = ['publicList', 'maps_map']}
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

{* Dirty trick to set active accordion fold using in_array tests on title and
content divs. Would be better to assign this array from model *}
{$configuration_routes = ['preferences', 'plugins', 'configureListFields', 'configureCoreFields', 'configureDynamicFields', 'dynamicTranslations', 'entitleds', 'texts', 'titles', 'pdfModels', 'paymentTypes', 'emptyAdhesionForm', 'adminTools']}
{if $login->isAdmin()}
                        <div class="item">
                            <div class="image header title{if $cur_route|in_array:$configuration_routes} active{/if}">
                                <i class="tools icon"></i>
                                {_T string="Configuration"}
                                <i class="dropdown icon"></i>
                            </div>
                            <div class="content{if $cur_route|in_array:$configuration_routes} active{/if}">
                                    <a href="{path_for name="preferences"}" title="{_T string="Set applications preferences (address, website, member's cards configuration, ...)"}" class="{if $cur_route eq "preferences"}active {/if}item">{_T string="Settings"}</a>
                                    <a href="{path_for name="plugins"}" title="{_T string="Informations about available plugins"}" class="{if $cur_route eq "plugins"}active {/if}item">{_T string="Plugins"}</a>
                                    <a href="{path_for name="configureListFields" data=["table" => "adherents"]}" title="{_T string="Customize lists fields and order"}" class="{if $cur_route eq "configureListFields"}active {/if}item">{_T string="Core lists"}</a>
                                    <a href="{path_for name="configureCoreFields"}" title="{_T string="Customize fields order, set which are required, and for who they're visibles"}" class="{if $cur_route eq "configureCoreFields"}active {/if}item">{_T string="Core fields"}</a>
                                    <a href="{path_for name="configureDynamicFields"}" title="{_T string="Manage additional fields for various forms"}" class="{if $cur_route eq "configureDynamicFields" or $cur_route eq 'editDynamicField'}active {/if}item">{_T string="Dynamic fields"}</a>
                                    <a href="{path_for name="dynamicTranslations"}" title="{_T string="Translate additionnals fields labels"}" class="{if $cur_route eq "dynamicTranslations"}active {/if}item">{_T string="Translate labels"}</a>
                                    <a href="{path_for name="entitleds" data=["class" => "status"]}" title="{_T string="Manage statuses"}" class="{if $cur_route eq "entitleds" and $cur_subroute eq "status"}active {/if}item">{_T string="Manage statuses"}</a>
                                    <a href="{path_for name="entitleds" data=["class" => "contributions-types"]}" title="{_T string="Manage contributions types"}" class="{if $cur_route eq "entitleds" and $cur_subroute eq "contributions-types"}active {/if}item">{_T string="Contributions types"}</a>
                                    <a href="{path_for name="texts"}" title="{_T string="Manage emails texts and subjects"}" class="{if $cur_route eq "texts"}active {/if}item">{_T string="Emails content"}</a>
                                    <a href="{path_for name="titles"}" title="{_T string="Manage titles"}" class="{if $cur_route eq "titles"}active {/if}item">{_T string="Titles"}</a>
                                    <a href="{path_for name="pdfModels"}" title="{_T string="Manage PDF models"}" class="{if $cur_route eq "pdfModels"}active {/if}item">{_T string="PDF models"}</a>
                                    <a href="{path_for name="paymentTypes"}" title="{_T string="Manage payment types"}" class="{if $cur_route eq "paymentTypes"}active {/if}item">{_T string="Payment types"}</a>
                                    <a href="{path_for name="emptyAdhesionForm"}" title="{_T string="Download empty adhesion form"}" class="item">{_T string="Empty adhesion form"}</a>
                    {if $login->isSuperAdmin()}
                                    <a href="{path_for name="adminTools"}" title="{_T string="Various administrative tools"}" class="{if $cur_route eq "adminTools"}active {/if}item">{_T string="Admin tools"}</a>
                    {/if}
                            </div>
                        </div>
{/if}
                        <div class="language item" title="{_T string="Choose your language"}">
                            <div class="image header title">
                                <i class="icon language" aria-hidden="true"></i>
                                <span>{$galette_lang}</span>
                                <i class="dropdown icon"></i>
                            </div>
                            <div class="content">
    {foreach item=langue from=$languages}
        {if $langue->getAbbrev() neq $galette_lang}
                                <a href="?ui_pref_lang={$langue->getID()}" title="{_T string="Switch locale to '%locale'" pattern="/%locale/" replace=$langue->getName()}" class="item">
                                    {$langue->getName()} <span>({$langue->getAbbrev()})</span>
                                </a>
        {/if}
    {/foreach}
                            </div>
                        </div>
                    </div>
{if $login->isLogged()}
                    <div class="ui segment">
                        <div class="ui basic center aligned fitted segment"><span class="ui tiny header">{$login->loggedInAs()}</span></div>
                        <a class="ui fluid red basic button" href="{if $login->isImpersonated()}{path_for name="unimpersonate"}{else}{path_for name="logout"}{/if}"><i class="icon {if $login->isImpersonated()}user secret{else}sign out alt{/if}"></i>{_T string="Log off"}</a>
                    </div>
{/if}
                </aside>
