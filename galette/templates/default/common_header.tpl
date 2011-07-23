{*
These file contains common html headers to include for Galette Smarty rendering.

Just put a {include file='common_header.tpl'} into the head tag.
*}
<title>{if $pref_slogan ne ""}{$pref_slogan} - {/if}{if $page_title ne ""}{$page_title} - {/if}Galette {$GALETTE_VERSION}</title>
		<meta charset="UTF-8" />
		<link rel="stylesheet" type="text/css" href="{$template_subdir}galette.css" />
		<script type="text/javascript" src="{$jquery_dir}jquery-{$jquery_version}.min.js"></script>
        {* IE8 doe not know html5 tags *}
        <!--[if lt IE9]>
            <script type="text/javascript" src="{$scripts_dir}html5-ie.js"></script>
        <!endif]-->
		<script type="text/javascript" src="{$jquery_dir}jquery.bgFade.js"></script>
		<script type="text/javascript" src="{$jquery_dir}chili-1.7.pack.js"></script>
		<script type="text/javascript" src="{$jquery_dir}jquery.tooltip.pack.js"></script>
        <script type="text/javascript" src="{$scripts_dir}common.js"></script>
        <meta name="viewport" content="width=device-width" />
