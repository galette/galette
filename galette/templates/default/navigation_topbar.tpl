        <header id="top-navbar" class="ui large top fixed menu bgcolor">
            <div class="ui fluid container">
                <a class="toc item">
                    <i class="sidebar icon"></i>
                </a>
                <div class="header item">
                    <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" class="logo" />
                    <span>{$preferences->pref_nom}</span>
                </div>
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
                <div class="{if $cur_route eq 'mailing' or ($cur_route neq 'mailing' and $existing_mailing eq false)}right {/if}ui dropdown right-aligned item{if $login->isAdmin()} is-admin{elseif $login->isStaff()} is-staff{/if}">
                    <i class="icon {if !$login->isSuperAdmin()}user circle outline{else}user shield{/if}" aria-hidden="true"></i>
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
{/if}
                <div class="language ui dropdown right-aligned item">
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
            </div>
        </header>
