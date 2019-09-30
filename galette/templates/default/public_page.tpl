<!DOCTYPE html>
<html lang="{$galette_lang}" class="public_page{if $additionnal_html_class} {$additionnal_html_class}{/if}">
    <head>
        {include file='common_header.tpl'}
{if $require_calendar}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.datepicker.min.js"></script>
    {if $galette_lang ne 'en'}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/i18n/jquery.ui.datepicker-{$galette_lang}.min.js"></script>
    {/if}
{/if}
{if $require_dialog}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.mouse.min.js"></script>
        {* Drag component, only used for Dialog for the moment *}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.draggable.min.js"></script>
        {* So the dialog could be aligned in the middle of the screen *}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.position.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.dialog.min.js"></script>
{/if}
{if $autocomplete}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.menu.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.autocomplete.min.js"></script>
        <script type="text/javascript">
            $(function() {
    {if $require_calendar}
                $.datepicker.setDefaults($.datepicker.regional['{$galette_lang}']);
        {if $galette_lang eq 'en'}
                    $.datepicker.setDefaults({
                        dateFormat: 'yy-mm-dd'
                    });
        {/if}
    {/if}
                $('#ville_adh, #lieu_naissance').autocomplete({
                    source: function (request, response) {
                        $.post('{path_for name="suggestTown"}', request, response);
                    },
                    minLength: 2
                });
                $('#pays_adh').autocomplete({
                    source: function (request, response) {
                        $.post('{path_for name="suggestCountry"}', request, response);
                    },
                    minLength: 2
                });
            });
    </script>
{/if}
{* If some additionnals headers should be added from plugins, we load the relevant template file
We have to use a template file, so Smarty will do its work (like replacing variables). *}
{if $headers|@count != 0}
    {foreach from=$headers item=header key=mid}
        {include file=$header module_id=$mid}
    {/foreach}
{/if}
{if $head_redirect}
    <meta http-equiv="refresh" content="{$head_redirect.timeout};url={$head_redirect.url}" />
{/if}
    </head>
    <body>
{if isset($GALETTE_DISPLAY_ERRORS) && $GALETTE_DISPLAY_ERRORS && $GALETTE_MODE != 'DEV'}
        <div id="oldie">
            <p>{_T string="Galette is configured to display errors. This must be avoided in production environments."}</p>
        </div>
{/if}
        {* IE8 and above are no longer supported *}
        <!--[if lte IE 8]>
        <div id="oldie">
            <p>{_T string="Your browser version is way too old and no longer supported in Galette for a while."}</p>
            <p>{_T string="Please update your browser or use an alternative one, like Mozilla Firefox (http://mozilla.org)."}</p>
        </div>
        <![endif]-->
        <header class="top-navbar gg columns">
            <div class="top-logo-name gg column clearfix">
                <div class="top-logo">
                    <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" />
                </div>
                <div class="top-name">
                    <p>{$preferences->pref_nom}{if $preferences->pref_slogan}<br />{$preferences->pref_slogan}{/if}</p>
                </div>
            </div>
            <div class="gg column row">
                <nav class="main-nav gg column">
                    <ul class="main-menu menu-ng horizontal">
                        <li class="item">
                            <a
                                href="{path_for name="slash"}"
                                class="{if $cur_route eq "slash" or $cur_route eq 'login'}selected{/if}"
                            >
                                <i class="fas fa-home"></i>
                                {_T string="Home"}
                            </a>
                        </li>
{if $preferences->showPublicPages($login) eq true}
                        <li class="item">
                            <a
                                href="{path_for name="publicList" data=["type" => "list"]}" title="{_T string="Members list"}"
                                class="{if $cur_route eq "publicList" and $cur_subroute eq "list"}selected{/if}"
                            >
                                <i class="fas fa-address-book"></i>
                                {_T string="Members list"}
                            </a>
                        </li>
                        <li class="item">
                            <a
                                class="{if $cur_route eq "publicList" and $cur_subroute eq "trombi"}selected{/if}"
                                href="{path_for name="publicList" data=["type" => "trombi"]}" title="{_T string="Trombinoscope"}"
                            >
                                <i class="fas fa-user-friends"></i>
                                {_T string="Trombinoscope"}
                            </a>
                        </li>
                        {* Include plugins menu entries *}
                        {*$plugins->getPublicMenus($tpl, true)*}
{/if}
                    </ul>
                </nav>
                <nav class="user-nav gg column">
                    <ul class="user-menu menu-ng horizontal">
{if !$login->isLogged()}
    {if $preferences->pref_bool_selfsubscribe eq true}
                        <li class="item">
                            <a
                                href="{path_for name="subscribe"}"
                                class="button{if $cur_route eq "subscribe"} selected{/if}"
                            >
                                <i class="fas fa-user-graduate" aria-hidden="true"></i>
                                {_T string="Subscribe"}
                            </a>
                        </li>
    {/if}
    {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED')}
                        <li class="item">
                            <a
                                href="{path_for name="password-lost"}"
                                class="button{if $cur_route eq "password-lost"} selected{/if}"
                            >
                                <i class="fas fa-unlock-alt" aria-hidden="true"></i>
                                {_T string="Lost your password?"}
                            </a>
                        </li>
    {/if}
{/if}
{if $login->isLogged()}
                        <li class="item">
                            <a class="button" title="{_T string="View your member card"}" href="{if $login->isSuperAdmin()}{path_for name="slash"}{else}{path_for name="me"}{/if}">{$login->loggedInAs(true)}</a>
                        </li>
                        <li class="item">
                            <a class="button" href="{if $login->isImpersonated()}{path_for name="unimpersonate"}{else}{path_for name="logout"}{/if}"><i class="fas fa-{if $login->isImpersonated()}user-secret{else}sign-out-alt{/if}"></i><span class="sr-only">{_T string="Log off"}</span></a>
                        </li>
{/if}
                    </ul>
                    <ul class="lang-menu menu-ng horizontal">
                        <li class="item dropdown">
                            <span title="{_T string="Choose your language"}">
                                Lang{*$langue->getAbbrev()*}
                            </span>
                            <ul class="gg menu-ng">
{foreach item=langue from=$languages}
                                <li class="item">
                                    <a href="?pref_lang={$langue->getID()}" title="{_T string="Switch locale to '%locale'" pattern="/%locale/" replace=$langue->getName()}">
                                        {$langue->getAbbrev()}
                                    </a>
                                </li>
{/foreach}
                            </ul>
                        </li>
                    </ul>
                </nav>
            </div>
        </header>
        <section class="main">
{if $GALETTE_MODE eq 'DEMO'}
            <div id="demo" title="{_T string="This application runs under DEMO mode, all features may not be available."}">
                {_T string="Demonstration"}
            </div>
{/if}
            <h1 id="titre">{$page_title}</h1>
            {include file="global_messages.tpl"}
            {block name="content"}{_T string="Public page content"}{/block}
        </section>
        {include file="footer.tpl"}
        {block name="javascripts"}{/block}
    </body>
</html>
