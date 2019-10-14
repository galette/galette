<!DOCTYPE html>
<html lang="{$galette_lang}">
    <head>
        {include file='common_header.tpl'}
{if $color_picker}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}farbtastic.js"></script>
        <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}farbtastic.css"/>
{/if}
{* JQuery UI related *}
{if $require_sorter or $require_dialog}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.mouse.min.js"></script>
{/if}
{if $require_sorter}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.sortable.min.js"></script>
{/if}
{if $require_calendar}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.datepicker.min.js"></script>
    {if $galette_lang ne 'en'}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/i18n/jquery.ui.datepicker-{$galette_lang}.min.js"></script>
    {/if}
{/if}
{if $require_tabs}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.tabs.min.js"></script>
{/if}
{if $require_dialog}
        {* Drag component, only used for Dialog for the moment *}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.draggable.min.js"></script>
        {* So the dialog could be aligned in the middle of the screen *}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.position.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.dialog.min.js"></script>
{/if}
{* /JQuery UI related *}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery.cookie.js"></script>
{if $require_charts}
        <link rel="stylesheet" type="text/css" href="{base_url}/{$jquery_dir}jqplot-{$jquery_jqplot_version}/jquery.jqplot.css" />
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jqplot-{$jquery_jqplot_version}/jquery.jqplot.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jqplot-{$jquery_jqplot_version}/plugins/jqplot.pieRenderer.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jqplot-{$jquery_jqplot_version}/plugins/jqplot.barRenderer.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jqplot-{$jquery_jqplot_version}/plugins/jqplot.pointLabels.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jqplot-{$jquery_jqplot_version}/plugins/jqplot.categoryAxisRenderer.min.js"></script>
{/if}
{if $html_editor}
    {if !isset($plugged_html_editor)}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}markitup-{$jquery_markitup_version}/jquery.markitup.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}markitup-{$jquery_markitup_version}/sets/html/set-{$galette_lang}.js"></script>
        <link rel="stylesheet" type="text/css" href="{base_url}/{$jquery_dir}markitup-{$jquery_markitup_version}/skins/galette/style.css" />
        <link rel="stylesheet" type="text/css" href="{base_url}/{$jquery_dir}markitup-{$jquery_markitup_version}/sets/html/style.css" />
        <script language="javascript">
            function toggleMailingEditor(id) {
                if(!$('#mailing_html').attr('checked')){
                    $('#mailing_html').attr('checked', true);
                }

                $('input#html_editor_active').attr('value', '1');
                {* While it is not possible to deactivate markItUp, we remove completly the functionnality *}
                $('#toggle_editor').remove();
                $('#mailing_corps').markItUp(galetteSettings);
            }
        {if $html_editor_active eq 1}
            $(document).ready(function(){
                {* While it is not possible to deactivate markItUp, we remove completly the functionnality *}
                $('#toggle_editor').remove();
                $('#mailing_corps').markItUp(galetteSettings);
            });
        {/if}
        </script>
    {/if}
{/if}
{if $require_tree}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery.jstree.js"></script>
{/if}
{if $autocomplete}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.menu.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.autocomplete.min.js"></script>
        <script type="text/javascript">
            $(function() {
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
{if $require_mass}
        <script type="text/javascript" src="{base_url}/{$scripts_dir}mass_changes.js"></script>
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
    <body id="galette_body">
        {include file="navigation_sidebar.tpl"}
        {include file="navigation_topbar.tpl"}
        <div class="pusher">
            <div id="main" class="ui main column horizontally padded grid">
{if $login->isLogged()}
                {include file="navigation_aside.tpl"}
{/if}
                <section class="ui sixteen wide {if $login->isLogged()}mobile thirteen wide computer{/if} column {if $contentcls}{$contentcls}{/if}">
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
{if !$login->isLogged()}
                        <div class="ui center aligned icon header">
                            <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" class="icon" />
                            <div class="content">
                               {$preferences->pref_nom}
                                <div class="sub header">{if $preferences->pref_slogan}{$preferences->pref_slogan}{/if}</div>
                            </div>
                        </div>
{/if}
                        <h1 class="ui block center aligned header">
                            {$page_title}
                        </h1>
                        {include file="global_messages.tpl"}
                        {*$content*}
                        {block name="content"}{_T string="Page content"}{/block}
                        {include file="footer.tpl"}
                    </div>
                </section>
{if !$login->isLogged()}
            </div>
{/if}
        </div>
        <a href="#" id="back2top" class="ui basic icon button">
            <i class="arrow up icon"></i>
            {_T string="Back to top"}
        </a>
        
        {block name="javascripts"}{/block}
        <script type="text/javascript">
            $(function(){
{if $require_calendar}
                $.datepicker.setDefaults($.datepicker.regional['{$galette_lang}']);
    {if $galette_lang eq 'en'}
                $.datepicker.setDefaults({
                    dateFormat: 'yy-mm-dd'
                });
    {/if}
{/if}
{if isset($renew_telemetry)}
    {include file="telemetry.tpl" part="jsdialog"}
{/if}
            });
        </script>
    </body>
</html>
