        <header id="top-navbar" class="ui large top fixed menu">
            <div class="ui fluid container">
                <a class="toc item">
                    <i class="sidebar icon"></i>
                </a>
                <div class="header item">
                    <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" class="logo" />
                    <span>{$preferences->pref_nom}</span>
                </div>
    {if $preferences->showPublicPages($login) eq true}
                <a
                    href="{path_for name="publicList" data=["type" => "list"]}" title="{_T string="Members list"}"
                    class="{if $cur_route eq "publicList" and $cur_subroute eq "list"}active {/if}item"
                    data-position="bottom center"
                >
                    <i class="icon address book" aria-hidden="true"></i>
                    {_T string="Members list"}
                </a>
                <a
                    href="{path_for name="publicList" data=["type" => "trombi"]}" title="{_T string="Trombinoscope"}"
                    class="{if $cur_route eq "publicList" and $cur_subroute eq "trombi"}active {/if}item"
                    data-position="bottom center"
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
                        data-position="bottom center"
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
                        data-position="bottom center"
                    >
                        <i class="icon sign in alt" aria-hidden="true"></i>
                        {_T string="Login"}
                    </a>
    {/if}
                </div>
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
