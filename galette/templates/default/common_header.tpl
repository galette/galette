{*
These file contains common html headers to include for Galette Smarty rendering.

Just put a {include file='common_header.tpl'} into the head tag.
*}
<title>{if $pref_slogan ne ""}{$pref_slogan} - {/if}{if $page_title ne ""}{$page_title} - {/if}Galette {$GALETTE_VERSION}</title>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width" />
        <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}galette.css" />
        <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}semantic/semantic.min.css" />
        <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}galette-ng.css" />
        <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}galette-transition.css" />
        <link rel="stylesheet" type="text/css" href="{base_url}/fontawesome-free-5.7.2/css/all.min.css" />
        <link rel="stylesheet" type="text/css" href="{base_url}/js/selectize-0.12.6/css/selectize.css" />
        <link rel="stylesheet" type="text/css" href="{base_url}/js/selectize-0.12.6/css/selectize.default.css" />
        {* Let's see if a local CSS exists and include it *}
        {assign var="localstylesheet" value="`$_CURRENT_THEME_PATH`galette_local.css"}
        {if file_exists($localstylesheet)}
            <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}/galette_local.css" />
        {/if}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-{$jquery_version}.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$scripts_dir}common.js"></script>
        <script type="text/javascript" src="{base_url}/{$template_subdir}semantic/semantic.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$scripts_dir}common-ng.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-migrate-{$jquery_migrate_version}.min.js"></script>
        <script type="text/javascript" src="{base_url}/js/selectize-0.12.6/js/standalone/selectize.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery.bgFade.js"></script>
        {* UI accordion is used for main menu ; we have to require it and UI core *}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.core.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.widget.min.js"></script>
        {*<script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.accordion.min.js"></script>*}
        {* Buttons can be used everywhere *}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.button.min.js"></script>
        {* Tooltips can be used everywhere *}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.position.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.tooltip.min.js"></script>
        {assign var="localjstracking" value="`$_CURRENT_THEME_PATH`tracking.js"}
        {if file_exists($localjstracking)}
            <script type="text/javascript" src="{base_url}/{$template_subdir}/tracking.js"></script>
        {/if}
        {* UI accordion is used for main menu ; we need the CSS *}
        <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}jquery-ui/jquery-ui-{$jquery_ui_version}.custom.css" />
        <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}galette_print.css" media="print" />
        {assign var="localprintstylesheet" value="`$_CURRENT_THEME_PATH`galette_print_local.css"}
        {if file_exists($localprintstylesheet)}
            <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}/galette_print_local.css" media="print" />
        {/if}
        <link rel="shortcut icon" href="{base_url}/{$template_subdir}images/favicon.png" />
