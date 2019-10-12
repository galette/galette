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
    <body{if $body_class eq "front_page"} class="front-page"{/if}>
        {include file='navigation_sidebar.tpl' page='public'}
        {include file='navigation_topbar.tpl'}
        <div class="pusher">
            <div id="main" class="ui column horizontally padded grid">
{if $login->isLogged()}
                {include file="navigation_aside.tpl"}
{/if}
                <section class="ui sixteen wide {if $login->isLogged()}mobile thirteen wide computer{/if} column">

{if !$login->isLogged()}
            <div class="ui container">
{/if}

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
{if $GALETTE_MODE eq 'DEMO'}
                        <div id="demo" title="{_T string="This application runs under DEMO mode, all features may not be available."}">
                        {_T string="Demonstration"}
                        </div>
{/if}
{if !$login->isLogged()}
                        <div class="ui center aligned icon header">
                            <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" class="icon" />
                            <div class="content">
                               {$preferences->pref_nom}
                                <div class="sub header">{if $preferences->pref_slogan}{$preferences->pref_slogan}{/if}</div>
                            </div>
                        </div>
{/if}
                        <h1 class="ui block center aligned header">{$page_title}</h1>
                        {include file="global_messages.tpl"}
                        {block name="content"}{_T string="Public page content"}{/block}
                        {include file="footer.tpl"}
{if !$login->isLogged()}
                </div>
{/if}
                </section>
            </div>
        </div>
        {block name="javascripts"}{/block}
    </body>
</html>
