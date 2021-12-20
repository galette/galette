{*
These file contains common html headers to include for Galette Smarty rendering.

Just put a {include file='common_header.tpl'} into the head tag.
*}
<title>{if $pref_slogan ne ""}{$pref_slogan} - {/if}{if $page_title ne ""}{$page_title} - {/if}Galette {$GALETTE_VERSION}</title>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width" />
        <link rel="stylesheet" type="text/css" href="{base_url}/assets/css/galette-main.bundle.min.css" />
        <link rel="stylesheet" type="text/css" href="{base_url}/assets/ui/semantic.min.css" />
        <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}galette-ng.css" />
        {* Let's see if a local CSS exists and include it *}
        {assign var="localstylesheet" value="`$_CURRENT_THEME_PATH`galette_local.css"}
        {if file_exists($localstylesheet)}
            <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}/galette_local.css" />
        {/if}
        <script type="text/javascript" src="{base_url}/assets/js/galette-main.bundle.min.js"></script>
    {if $require_charts}
        <link rel="stylesheet" type="text/css" href="{base_url}/assets/css/galette-jqplot.bundle.min.css" />
    {/if}
    {if $require_tree}
        <link rel="stylesheet" type="text/css" href="{base_url}/assets/css/galette-jstree.bundle.min.css"/>
    {/if}
    {if $html_editor}
        <link rel="stylesheet" type="text/css" href="{base_url}/{$jquery_dir}markitup-{$jquery_markitup_version}/skins/galette/style.css" />
        <link rel="stylesheet" type="text/css" href="{base_url}/{$jquery_dir}markitup-{$jquery_markitup_version}/sets/html/style.css" />
    {/if}
        <script type="text/javascript" src="{base_url}/assets/ui/semantic.min.js"></script>
        <script type="text/javascript" src="{base_url}/{$scripts_dir}common-ng.js"></script>
        <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}galette_print.css" media="print" />
    {assign var="localprintstylesheet" value="`$_CURRENT_THEME_PATH`galette_print_local.css"}
    {if file_exists($localprintstylesheet)}
        <link rel="stylesheet" type="text/css" href="{base_url}/{$template_subdir}/galette_print_local.css" media="print" />
    {/if}
        <link rel="shortcut icon" href="{base_url}/{$template_subdir}images/favicon.png" />
