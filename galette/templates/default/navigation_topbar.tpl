        <header id="top-navbar" class="ui top fixed pointing menu bg-color">
            <div class="ui fluid container">
                <a class="toc item">
                    <i class="sidebar icon"></i>
                </a>
                <div class="header item">
                    <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" class="logo" />
                    <span>{$preferences->pref_nom}{if $preferences->pref_slogan}<br>{$preferences->pref_slogan}{/if}</span>
                </div>
{if $cur_route neq "login"}
    {if $login->isLogged()}
                <a
                    href="{path_for name="dashboard"}"
                    title="{_T string="Go to Galette's dashboard"}"
                    class="{if $cur_route eq 'dashboard'}active {/if}item"
                >
                    <i class="icon compass" aria-hidden="true"></i>
                    {_T string="Dashboard"}
                </a>
    {else}
        <a
                    href="{path_for name="slash"}"
                    title="{_T string="Go back to Galette homepage"}"
                    class="{if $cur_route eq "slash"}active {/if}item"
                >
                    <i class="icon home" aria-hidden="true"></i>
                    {_T string="Home"}
                </a>
    {/if}
{/if}
{if !$login->isLogged()}
    {if $preferences->showPublicPages($login) eq true}
                <a
                    href="{path_for name="publicList" data=["type" => "list"]}" title="{_T string="Members list"}"
                    class="{if $cur_route eq "publicList" and $cur_subroute eq "list"}active {/if}item"
                >
                    <i class="icon address book" aria-hidden="true"></i>
                    {_T string="Members list"}
                </a>
                <a
                    href="{path_for name="publicList" data=["type" => "trombi"]}" title="{_T string="Trombinoscope"}"
                    class="{if $cur_route eq "publicList" and $cur_subroute eq "trombi"}active {/if}item"
                >
                    <i class="icon user friends" aria-hidden="true"></i>
                    {_T string="Trombinoscope"}
                </a>
                {* Include plugins menu entries *}
                {$plugins->getPublicMenus($tpl, true)}
    {/if}
                <div class="right item">
    {if $preferences->pref_bool_selfsubscribe eq true and $cur_route neq "subscribe"}
                    <a
                        href="{path_for name="subscribe"}"
                        class="ui basic button"
                        title="{_T string="Subscribe"}"
                    >
                        <i class="icon add user" aria-hidden="true"></i>
                        {_T string="Subscribe"}
                    </a>
    {/if}
    {if $cur_route neq "login"}
                    <a
                        href="{path_for name="slash"}"
                        class="ui primary button"
                        title="{_T string="Login"}"
                    >
                        <i class="icon sign in alt" aria-hidden="true"></i>
                        {_T string="Login"}
                    </a>
    {/if}
                </div>
{else}
    {if $cur_route neq 'mailing' and $existing_mailing eq true}
                <div class="tray right item">
                    <a
                        href="{path_for name="mailing"}"
                        class="tooltip ui yellow circular large label"
                        title="{_T string="A mailing exists in the current session. Click here if you want to resume or cancel it."}"
                    >
                        <i class="mail bulk icon"></i>
                        <span class="hidden">{_T string="Existing mailing"}</span>
                    </a>
                </div>
    {/if}
                <div class="{if $cur_route eq 'mailing' or ($cur_route neq 'mailing' and $existing_mailing eq false)}right {/if}ui simple dropdown right-aligned item{if $login->isAdmin()} is-admin{elseif $login->isStaff()} is-staff{/if}">
                    <i class="icon {if !$login->isSuperAdmin()}user circle outline{else}superpowers{/if}" aria-hidden="true"></i>
                    <span>{$login->loggedInAs(true)}</span>
                    <i class="icon dropdown" aria-hidden="true"></i>
                    <div class="menu">
    {if !$login->isSuperAdmin()}
                        <a href="{path_for name="me"}" title="{_T string="View my member card"}" class="{if $cur_route eq "me" or $cur_route eq "member"}active {/if}item">{_T string="My information"}</a>
                        <a href="{path_for name="contributions" data=["type" => "contributions"]}" title="{_T string="View and filter all my contributions"}" class="{if $cur_route eq "contributions" and $cur_subroute eq "contributions"}active {/if}item">{_T string="My contributions"}</a>
                        <a href="{path_for name="contributions" data=["type" => "transactions"]}" title="{_T string="View and filter all my transactions"}" class="{if $cur_route eq "contributions" and $cur_subroute eq "transactions"}active {/if}item">{_T string="My transactions"}</a>
    {/if}
                        <div class="item">
                            <a class="ui fluid item button" href="{if $login->isImpersonated()}{path_for name="unimpersonate"}{else}{path_for name="logout"}{/if}"><i class="icon {if $login->isImpersonated()}user secret{else}sign out alt{/if}"></i>{_T string="Log off"}</a>
                        </div>
                    </div>
                </div>
    {if $login->isAdmin()}
                <div class="ui simple dropdown right-aligned item{if $login->isAdmin()} is-admin{elseif $login->isStaff()} is-staff{/if}">
                    <i class="icon tools" aria-hidden="true"></i>
                    <span class="hidden">{_T string="Configuration"}</span>
                    <i class="icon dropdown" aria-hidden="true"></i>
                    <div class="menu">
                        <a href="{path_for name="preferences"}" title="{_T string="Set applications preferences (address, website, member's cards configuration, ...)"}" class="item">{_T string="Settings"}</a>
                        <a href="{path_for name="plugins"}" title="{_T string="Informations about available plugins"}" class="item">{_T string="Plugins"}</a>
                        <a href="{path_for name="configureCoreFields"}" title="{_T string="Customize fields order, set which are required, and for who they're visibles"}" class="item">{_T string="Core fields"}</a>
                        <a href="{path_for name="configureDynamicFields"}" title="{_T string="Manage additional fields for various forms"}" class="item">{_T string="Dynamic fields"}</a>
                        <a href="{path_for name="dynamicTranslations"}" title="{_T string="Translate additionnals fields labels"}" class="item">{_T string="Translate labels"}</a>
                        <a href="{path_for name="entitleds" data=["class" => "status"]}" title="{_T string="Manage statuses"}" class="item">{_T string="Manage statuses"}</a>
                        <a href="{path_for name="entitleds" data=["class" => "contributions-types"]}" title="{_T string="Manage contributions types"}" class="item">{_T string="Contributions types"}</a>
                        <a href="{path_for name="texts"}" title="{_T string="Manage emails texts and subjects"}" class="item">{_T string="Emails content"}</a>
                        <a href="{path_for name="titles"}" title="{_T string="Manage titles"}" class="item">{_T string="Titles"}</a>
                        <a href="{path_for name="pdfModels"}" title="{_T string="Manage PDF models"}" class="item">{_T string="PDF models"}</a>
                        <a href="{path_for name="paymentTypes"}" title="{_T string="Manage payment types"}" class="item">{_T string="Payment types"}</a>
                        <a href="{path_for name="emptyAdhesionForm"}" title="{_T string="Download empty adhesion form"}" class="item">{_T string="Empty adhesion form"}</a>
        {if $login->isSuperAdmin()}
                        <a href="{path_for name="fakeData"}" class="item">{_T string="Generate fake data"}</a>
                        <a href="{path_for name="adminTools"}" title="{_T string="Various administrative tools"}" class="item">{_T string="Admin tools"}</a>
                    </div>
        {/if}
                </div>
    {/if}
{/if}
                <div class="language ui simple dropdown right-aligned item">
                    <i class="icon language" aria-hidden="true"></i>
                    <span>{$galette_lang}</span>
                    <i class="icon dropdown" aria-hidden="true"></i>
                    <div class="menu">
{foreach item=langue from=$languages}
    {if $langue->getAbbrev() neq $galette_lang}
                        <a href="?ui_pref_lang={$langue->getID()}" title="{_T string="Switch locale to '%locale'" pattern="/%locale/" replace=$langue->getName()}" class="item">
                            {$langue->getName()} <span>({$langue->getAbbrev()})</span>
                        </a>
    {/if}
{/foreach}
                    </div>
                </div>
        </header>
