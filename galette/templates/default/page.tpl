<!DOCTYPE html>
<html lang="{$galette_lang}">
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
    <body id="galette_body" class="pushable dimmable{if $login->isLogged()} loggedin{/if}">
        {include file="navigation/navigation_sidebar.tpl"}
        {include file="navigation/navigation_topbar.tpl"}
        <div class="pusher">
            <div id="main" class="{if !$login->isLogged()}container{else}full height{/if}">
{if $login->isLogged()}
                {include file="navigation/navigation_aside.tpl"}
{/if}
                <section class="content{if $contentcls} {$contentcls}{/if}">
{if isset($GALETTE_DISPLAY_ERRORS) && $GALETTE_DISPLAY_ERRORS && $GALETTE_MODE != 'DEV'}
                    <div class="ui tiny red message">
                        {_T string="Galette is configured to display errors. This must be avoided in production environments."}
                    </div>
{/if}

{if !$login->isLogged()}
                    <div class="ui basic center aligned fitted segment">
                        <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="{$preferences->pref_nom}" class="icon"/>
                        <div class="ui large header">
                            {$preferences->pref_nom}
                            <div class="sub header">{if $preferences->pref_slogan}{$preferences->pref_slogan}{/if}</div>
                        </div>
                    </div>
{/if}
                    <h1 class="ui block center aligned header" style="position: relative">
                        {block name="page_title"}{$page_title}{/block}
            {if $cur_route neq 'mailing' and $existing_mailing eq true}
                        <a
                            id="recup_mailing"
                            href="{path_for name="mailing"}"
                            class="ui basic tertiary secondary huge right floated button tooltip"
                            title="{_T string="A mailing exists in the current session. Click here if you want to resume or cancel it."}"
                            data-position="bottom right"
                        >
                            <i class="mail bulk icon"></i>
                            <span class="sr-only">{_T string="Existing mailing"}</span>
                        </a>
            {/if}
                    </h1>
                    {include file="toasts.tpl"}
                    {*include file="global_messages.tpl"*}
                    {block name="content"}{_T string="Page content"}{/block}
                    {include file="footer.tpl"}
                </section>
            </div>
        </div>
        <a href="#" id="back2top" class="ui basic icon button">
            <i class="arrow up icon"></i>
            {_T string="Back to top"}
        </a>
        {include file="common_scripts.tpl"}
        {block name="javascripts"}{/block}
    </body>
</html>
