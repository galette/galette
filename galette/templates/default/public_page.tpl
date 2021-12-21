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
    <body class="{if isset($body_class) and $body_class eq "front_page"}front-page {/if}pushable{if $login->isLogged()} loggedin{/if}">
        {include file='navigation_sidebar.tpl' page='public'}
        {include file='navigation_topbar.tpl'}
        <div class="pusher">
            <div id="main" class="{if $cur_route eq "login" or $cur_route eq "password-lost"} text{/if}{if !$login->isLogged()}ui container{else} full height{/if}">
{if $login->isLogged()}
                {include file="navigation_aside.tpl"}
{/if}
                <section{if $login->isLogged()} class="content"{/if}>
{if isset($GALETTE_DISPLAY_ERRORS) && $GALETTE_DISPLAY_ERRORS && $GALETTE_MODE != 'DEV'}
                    <div class="ui tiny red message">
                        {_T string="Galette is configured to display errors. This must be avoided in production environments."}
                    </div>
{/if}
{if $GALETTE_MODE eq 'DEMO'}
    {if $cur_route neq "editMember"}
        {if !$login->isLogged()}
            {if $cur_route eq ("addMember" or "login") or ($cur_route eq "publicList" and $cur_subroute eq ("list" or "trombi"))}
                    <div class="ui tiny orange message" title="{_T string="This application runs under DEMO mode, all features may not be available."}">
                        {_T string="Demonstration"}
                    </div>
            {/if}
        {/if}
    {/if}
{/if}
{if !$login->isLogged()}
                    <div class="ui basic center aligned fitted segment">
                        <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="{$preferences->pref_nom}" class="icon"/>
                        <div class="ui block huge brand header">
                            {$preferences->pref_nom}
                            <div class="sub header">{if $preferences->pref_slogan}{$preferences->pref_slogan}{/if}</div>
                        </div>
                    </div>
{/if}
                    <h1 class="ui block center aligned header">{$page_title}</h1>
                    {*include file="global_messages.tpl"*}
                    {block name="content"}{_T string="Public page content"}{/block}
                    {include file="footer.tpl"}
                </section>
            </div>
        </div>
        <a href="#" id="back2top" class="ui basic icon button">
            <i class="arrow up icon"></i>
            {_T string="Back to top"}
        </a>
        {include file="common_scripts.tpl"}
        {include file="toasts.tpl"}
        {block name="javascripts"}{/block}
    </body>
</html>
