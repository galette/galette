<!DOCTYPE html>
<html lang="{$galette_lang}" class="public_page{if $additionnal_html_class} {$additionnal_html_class}{/if}">
    <head>
        {include file='common_header.tpl'}
{* If some additionnals headers should be added from plugins, we load the relevant template file
We have to use a template file, so Smarty will do its work (like replacing variables). *}
{if $headers|@count != 0}
    {foreach from=$headers item=header key=mid}
        {include file=$header module_id=$mid}
    {/foreach}
{/if}
    </head>
    <body>
{if isset($GALETTE_DISPLAY_ERRORS) && $GALETTE_DISPLAY_ERRORS && $GALETTE_MODE != 'DEV'}
        <div id="oldie">
            <p>{_T string="Galette is configured to display errors. This must be avoided in production environments."}</p>
        </div>
{/if}
        <header>
            <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" />
            <nav id="plang_selector" class="onhover">
                <a href="#plang_selector" class="tooltip" aria-expanded="false" aria-controls="lang_selector" title="{_T string="Change language"}">
                    <i class="fas fa-language"></i>
                    {$galette_lang_name}
                </a>
                <ul id="lang_selector">
{foreach item=langue from=$languages}
                    <li {if $galette_lang eq $langue->getAbbrev()} selected="selected"{/if}>
                        <a href="?ui_pref_lang={$langue->getID()}" lang="{$langue->getAbbrev()}">{$langue->getName()}</a>
                    </li>
{/foreach}
                </ul>
            </nav>
{if $login->isLogged()}
            <div id="user">
                <a id="userlink" class="tooltip" title="{_T string="View your member card"}" href="{if $login->isSuperAdmin()}{path_for name="slash"}{else}{path_for name="me"}{/if}">{$login->loggedInAs(true)}</a>
                <a id="logout_10" class="tooltip" href="{if $login->isImpersonated()}{path_for name="unimpersonate"}{else}{path_for name="logout"}{/if}"><i class="fas fa-{if $login->isImpersonated()}user-secret{else}sign-out-alt{/if}"></i><span class="sr-only">{_T string="Log off"}</span></a>
            </div>
{/if}
{if $GALETTE_MODE eq 'DEMO'}
        <div id="demo" title="{_T string="This application runs under DEMO mode, all features may not be available."}">
            {_T string="Demonstration"}
        </div>
{/if}
        </header>
        <h1 id="titre">{$page_title}</h1>
        <p id="asso_name">{$preferences->pref_nom}{if $preferences->pref_slogan}&nbsp;: {$preferences->pref_slogan}{/if}</p>
        <nav>
            <a
                href="{path_for name="slash"}"
                class="button{if $cur_route eq "slash" or $cur_route eq 'login'} selected{/if}"
            >
                <i class="fas fa-home"></i>
                {_T string="Home"}
            </a>
    {if !$login->isLogged()}
        {if $preferences->pref_bool_selfsubscribe eq true}
            <a
                id="subscribe"
                href="{path_for name="subscribe"}"
                class="button{if $cur_route eq "subscribe"} selected{/if}"
            >
                <i class="fas fa-user-graduate" aria-hidden="true"></i>
                {_T string="Subscribe"}
            </a>
        {/if}
        {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED')}
            <a
                id="lostpassword"
                href="{path_for name="password-lost"}"
                class="button{if $cur_route eq "password-lost"} selected{/if}"
            >
                <i class="fas fa-unlock-alt" aria-hidden="true"></i>
                {_T string="Lost your password?"}
            </a>
        {/if}
    {/if}
    {if $preferences->showPublicPages($login) eq true}
            <a
                href="{path_for name="publicList" data=["type" => "list"]}" title="{_T string="Members list"}"
                class="button{if $cur_route eq "publicList" and $cur_subroute eq "list"} selected{/if}"
            >
                <i class="fas fa-address-book"></i>
                {_T string="Members list"}
            </a>
            <a
                class="button{if $cur_route eq "publicList" and $cur_subroute eq "trombi"} selected{/if}"
                href="{path_for name="publicList" data=["type" => "trombi"]}" title="{_T string="Trombinoscope"}"
            >
                <i class="fas fa-user-friends"></i>
                {_T string="Trombinoscope"}
            </a>
            {* Include plugins menu entries *}
            {$plugins->getPublicMenus($tpl, true)}
    {/if}
        </nav>
        {include file="global_messages.tpl"}
        {block name="content"}{_T string="Public page content"}{/block}
        {include file="footer.tpl"}
        {include file="common_scripts.tpl"}
        {block name="javascripts"}{/block}
    </body>
</html>
