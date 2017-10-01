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
    <div id="menu">
        <div id="logo">
            <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" />
        </div>
{if $login->isSuperAdmin()}
        <div id="superadmin" title="{_T string="You are actually logged-in as superadmin. Some functionnalities may not be available since this is *not* a regular member."}">
            {_T string="Superadmin"}
        </div>
{/if}
{if $GALETTE_MODE eq 'DEMO'}
        <div id="demo" title="{_T string="This application runs under DEMO mode, all features may not be available."}">
            {_T string="Demonstration"}
        </div>
{/if}
{if $login->isLogged()}
        <h1 class="nojs">{_T string="Navigation"}</h1>
        <ul>
  {if $login->isAdmin() or $login->isStaff() or $login->isGroupManager()}
            <li{if $cur_route eq "dashboard"} class="selected"{/if}><a href="{path_for name="dashboard"}" title="{_T string="Go to Galette's dashboard"}">{_T string="Dashboard"}</a></li>
            <li{if $cur_route eq "members"} class="selected"{/if}><a href="{path_for name="members"}" title="{_T string="View, search into and filter member's list"}">{_T string="List of members"}</a></li>
            <li{if $cur_route eq "advanced-search"} class="selected"{/if}><a href="{path_for name="advanced-search"}" title="{_T string="Perform advanced search into members list"}">{_T string="Advanced search"}</a></li>
            <li{if $cur_route eq "groups"} class="selected"{/if}><a href="{path_for name="groups"}" title="{_T string="View and manage groups"}">{_T string="Manage groups"}</a></li>
  {/if}
  {if $login->isAdmin() or $login->isStaff()}
            <li{if $cur_route eq "contributions" and $cur_subroute eq "contributions"} class="selected"{/if}><a href="{path_for name="contributions" data=["type" => {_T string="contributions" domain="routes"}]}" title="{_T string="View and filter contributions"}">{_T string="List of contributions"}</a></li>
            <li{if $cur_route eq "contributions" and $cur_subroute eq "transactions"} class="selected"{/if}><a href="{path_for name="contributions" data=["type" => {_T string="transactions" domain="routes"}]}" title="{_T string="View and filter transactions"}">{_T string="List of transactions"}</a></li>
            <li{if $cur_route eq "editmember"} class="selected"{/if}><a href="{path_for name="editmember" data=["action" => {_T string="add" domain="routes"}]}" title="{_T string="Add new member in database"}">{_T string="Add a member"}</a></li>
            <li{if $cur_route eq "contribution" and $cur_subroute eq "fee"} class="selected"{/if}><a href="{path_for name="contribution" data=["type" => {_T string="fee" domain="routes"}, "action" => {_T string="add" domain="routes"}]}" title="{_T string="Add new membership fee in database"}">{_T string="Add a membership fee"}</a></li>
            <li{if $cur_route eq "contribution" and $cur_subroute eq "donation"} class="selected"{/if}><a href="{path_for name="contribution" data=["type" => {_T string="donation" domain="routes"}, "action" => {_T string="add" domain="routes"}]}" title="{_T string="Add new donation in database"}">{_T string="Add a donation"}</a></li>
            <li{if $cur_route eq "transaction"} class="selected"{/if}><a href="{path_for name="transaction" data=["action" => {_T string="add" domain="routes"}]}" title="{_T string="Add new transaction in database"}">{_T string="Add a transaction"}</a></li>
            <li{if $cur_route eq "reminders"} class="selected"{/if}><a href="{path_for name="reminders"}" title="{_T string="Send reminders to late members"}">{_T string="Reminders"}</a></li>
            <li{if $cur_route eq "history"} class="selected"{/if}><a href="{path_for name="history"}" title="{_T string="View application's logs"}">{_T string="Logs"}</a></li>
            <li{if $cur_route eq "mailings"} class="selected"{/if}><a href="{path_for name="mailings"}" title="{_T string="Manage mailings that has been sent"}">{_T string="Manage mailings"}</a></li>
            <li{if $cur_route eq "export"} class="selected"{/if}><a href="{path_for name="export"}" title="{_T string="Export some data in various formats"}">{_T string="Exports"}</a></li>
            <li{if $cur_route eq "import" or $cur_route eq "importModel"} class="selected"{/if}><a href="{path_for name="import"}" title="{_T string="Import members from CSV files"}">{_T string="Imports"}</a></li>
            <li class="mnu_last{if $cur_route eq "charts"} selected{/if}"><a href="{path_for name="charts"}" title="{_T string="Various charts"}">{_T string="Charts"}</a></li>
  {else}
            <li{if $cur_route eq "contributions" and $cur_subroute eq "contributions"} class="selected"{/if}><a href="{path_for name="contributions" data=["type" => {_T string="contributions" domain="routes"}]}" title="{_T string="View and filter all my contributions"}">{_T string="My contributions"}</a></li>
            <li{if $cur_route eq "contributions" and $cur_subroute eq "transactions"} class="selected"{/if}><a href="{path_for name="contributions" data=["type" => {_T string="transactions" domain="routes"}]}" title="{_T string="View and filter all my transactions"}">{_T string="My transactions"}</a></li>
  {/if}
  {if !$login->isSuperAdmin()}
            <li{if $cur_route eq "me" or $cur_route eq "member"} class="selected"{/if}><a href="{path_for name="me"}" title="{_T string="View my member card"}">{_T string="My information"}</a></li>
  {/if}
        </ul>
{/if}
{if $preferences->showPublicPages($login) eq true}
        <h1 class="nojs">{_T string="Public pages"}</h1>
        <ul>
            <li><a href="{path_for name="publicMembers"}" title="{_T string="Members list"}">{_T string="Members list"}</a></li>
            <li><a href="{path_for name="publicTrombinoscope"}" title="{_T string="Trombinoscope"}">{_T string="Trombinoscope"}</a></li>
            {* Include plugins menu entries *}
            {$plugins->getPublicMenus($tpl)}
        </ul>
{/if}
{if $login->isAdmin()}
        <h1 class="nojs">{_T string="Configuration"}</h1>
        <ul>
            <li{if $cur_route eq "preferences"} class="selected"{/if}><a href="{path_for name="preferences"}" title="{_T string="Set applications preferences (address, website, member's cards configuration, ...)"}">{_T string="Settings"}</a></li>
            <li{if $cur_route eq "plugins"} class="selected"{/if}><a href="{path_for name="plugins"}" title="{_T string="Informations about available plugins"}">{_T string="Plugins"}</a></li>
            <li{if $cur_route eq "configureCoreFields"} class="selected"{/if}><a href="{path_for name="configureCoreFields"}" title="{_T string="Customize fields order, set which are required, and for who they're visibles"}">{_T string="Core fields"}</a></li>
            <li{if $cur_route eq "configureDynamicFields" or $cur_route eq 'editDynamicField'} class="selected"{/if}><a href="{path_for name="configureDynamicFields"}" title="{_T string="Manage additional fields for various forms"}">{_T string="Dynamic fields"}</a></li>
            <li{if $cur_route eq "dynamicTranslations"} class="selected"{/if}><a href="{path_for name="dynamicTranslations"}" title="{_T string="Translate additionnals fields labels"}">{_T string="Translate labels"}</a></li>
            <li{if $cur_route eq "entitleds" and $cur_subroute eq {_T string="status" domain="routes"}} class="selected"{/if}><a href="{path_for name="entitleds" data=["class" => {_T string="status" domain="routes"}]}" title="{_T string="Manage statuses"}">{_T string="Manage statuses"}</a></li>
            <li{if $cur_route eq "entitleds" and $cur_subroute eq {_T string="contributions-types" domain="routes"}} class="selected"{/if}><a href="{path_for name="entitleds" data=["class" => {_T string="contributions-types" domain="routes"}]}" title="{_T string="Manage contributions types"}">{_T string="Contributions types"}</a></li>
            <li{if $cur_route eq "texts"} class="selected"{/if}><a href="{path_for name="texts"}" title="{_T string="Manage emails texts and subjects"}">{_T string="Emails content"}</a></li>
            <li{if $cur_route eq "titles"} class="selected"{/if}><a href="{path_for name="titles"}" title="{_T string="Manage titles"}">{_T string="Titles"}</a></li>
            <li{if $cur_route eq "pdfModels"} class="selected"{/if}><a href="{path_for name="pdfModels"}" title="{_T string="Manage PDF models"}">{_T string="PDF models"}</a></li>
            <li><a href="{path_for name="emptyAdhesionForm"}" title="{_T string="Download empty adhesion form"}">{_T string="Empty adhesion form"}</a></li>
    {if $login->isSuperAdmin()}
            <li{if $cur_route eq "fakeData"} class="selected"{/if}><a href="{path_for name="fakeData"}">{_T string="Generate fake data"}</a></li>
            <li{if $cur_route eq "adminTools"} class="selected"{/if}><a href="{path_for name="adminTools"}" title="{_T string="Various administrative tools"}">{_T string="Admin tools"}</a></li>
    {/if}
        </ul>
{/if}

{* Include plugins menu entries *}
{$plugins->getMenus($tpl)}

        <ul id="langs">
{foreach item=langue from=$languages}
            <li><a href="?pref_lang={$langue->getID()}"><img src="{base_url}/{$langue->getFlag()}" alt="{$langue->getName()}" lang="{$langue->getAbbrev()}" class="flag"/></a></li>
{/foreach}
        </ul>
{if $login->isLogged()}
        <div>{$login->loggedInAs()}</div>
        <a id="{if $login->isImpersonated()}unimpersonate{else}logout{/if}" class="button" href="{if $login->isImpersonated()}{path_for name="unimpersonate"}{else}{path_for name="logout"}{/if}">{_T string="Log off"}</a>
{/if}
    </div>
    <div id="content"{if $contentcls} class="{$contentcls}"{/if}>
        <h1 id="titre">
            <a href="#galette_body" class="nav-button-open" aria-label="open navigation"></a>
            <a href="#" class="nav-button-close" aria-label="close navigation"></a>
            {$page_title}
            {if $cur_route neq 'mailing' and $existing_mailing eq true}
                <a class="button" id="sendmail" href="{path_for name="mailing"}" title="{_T string="A mailing exists in the current session. Click here if you want to resume or cancel it."}">
                    {_T string="Existing mailing"}
                </a>
            {/if}
        </h1>
        <p id="asso_name">{$preferences->pref_nom}{if $preferences->pref_slogan}&nbsp;: {$preferences->pref_slogan}{/if}</p>

    {include file="global_messages.tpl"}
        {*$content*}
        {block name="content"}{_T string="Page content"}{/block}
    </div>
    {include file="footer.tpl"}
    {block name="javascripts"}{/block}
    {if isset($renew_telemetry)}
        <script type="text/javascript">
            $(function(){
        {include file="telemetry.tpl" part="jsdialog"}
            });
        </script>
    {/if}
</body>
</html>
