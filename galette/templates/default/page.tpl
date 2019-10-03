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
        <div class="ui left vertical menu sidebar">
{if $login->isLogged()}
            <div class="item">
                <a class="button" title="{_T string="View your member card"}" href="{if $login->isSuperAdmin()}{path_for name="slash"}{else}{path_for name="me"}{/if}">{$login->loggedInAs(true)}</a>
                <a class="button" href="{if $login->isImpersonated()}{path_for name="unimpersonate"}{else}{path_for name="logout"}{/if}">
                    <i class="icon {if $login->isImpersonated()}user secret{else}sign out alt{/if}"></i>
                    <span class="sr-only">{_T string="Log off"}</span>
                </a>
            </div>
            <div class="item" title="{_T string="Navigation"}">
                <div class="image header title">
                    <i class="icon compass outline" aria-hidden="true"></i>
                    {_T string="Navigation"}
                </div>
                <div class="menu">
                    <a href="{path_for name="dashboard"}" title="{_T string="Go to Galette's dashboard"}" class="{if $cur_route eq "dashboard"}active {/if}item">{_T string="Dashboard"}</a>
    {if $login->isAdmin() or $login->isStaff()}
                    <a href="{path_for name="members"}" title="{_T string="View, search into and filter member's list"}" class="{if $cur_route eq "members"}active {/if}item">{_T string="List of members"}</a>
                    <a href="{path_for name="advanced-search"}" title="{_T string="Perform advanced search into members list"}" class="{if $cur_route eq "advanced-search"}active {/if}item">{_T string="Advanced search"}</a>
                    <a href="{path_for name="searches"}" title="{_T string="Saved searches"}" class="{if $cur_route eq "searches"}active {/if}item">{_T string="Saved searches"}</a>
                    <a href="{path_for name="groups"}" title="{_T string="View and manage groups"}" class="{if $cur_route eq "groups"}active {/if}item">{_T string="Manage groups"}</a>
        {if $login->isAdmin() or $login->isStaff() or $login->isGroupManager()}
                    <a href="{path_for name="contributions" data=["type" => "contributions"]}" title="{_T string="View and filter contributions"}" class="{if $cur_route eq "contributions" and $cur_subroute eq "contributions"}active {/if}item">{_T string="List of contributions"}</a>
                    <a href="{path_for name="contributions" data=["type" => "transactions"]}" title="{_T string="View and filter transactions"}" class="{if $cur_route eq "contributions" and $cur_subroute eq "transactions"}active {/if}item">{_T string="List of transactions"}</a>
                    <a href="{path_for name="editmember" data=["action" => "add"]}" title="{_T string="Add new member in database"}" class="{if $cur_route eq "editmember"}active {/if}item">{_T string="Add a member"}</a>
                    <a href="{path_for name="contribution" data=["type" => "fee", "action" => "add"]}" title="{_T string="Add new membership fee in database"}" class="{if $cur_route eq "contribution" and $cur_subroute eq "fee"}active {/if}item">{_T string="Add a membership fee"}</a>
                    <a href="{path_for name="contribution" data=["type" => "donation", "action" => "add"]}" title="{_T string="Add new donation in database"}" class="{if $cur_route eq "contribution" and $cur_subroute eq "donation"}active {/if}item">{_T string="Add a donation"}</a>
                    <a href="{path_for name="transaction" data=["action" => "add"]}" title="{_T string="Add new transaction in database"}" class="{if $cur_route eq "transaction"}active {/if}item">{_T string="Add a transaction"}</a>
                    <a href="{path_for name="reminders"}" title="{_T string="Send reminders to late members"}" class="{if $cur_route eq "reminders"}active {/if}item">{_T string="Reminders"}</a>
                    <a href="{path_for name="history"}" title="{_T string="View application's logs"}" class="{if $cur_route eq "history"}active {/if}item">{_T string="Logs"}</a>
                    <a href="{path_for name="mailings"}" title="{_T string="Manage mailings that has been sent"}" class="{if $cur_route eq "mailings"}active {/if}item">{_T string="Manage mailings"}</a>
                    <a href="{path_for name="export"}" title="{_T string="Export some data in various formats"}" class="{if $cur_route eq "export"}active {/if}item">{_T string="Exports"}</a>
                    <a href="{path_for name="import"}" title="{_T string="Import members from CSV files"}" class="{if $cur_route eq "import" or $cur_route eq "importModel"}active {/if}item">{_T string="Imports"}</a>
                    <a href="{path_for name="charts"}" title="{_T string="Various charts"}" class="{if $cur_route eq "charts"} active{/if}item">{_T string="Charts"}</a>
        {else}
                    <a href="{path_for name="contributions" data=["type" => "contributions"]}" title="{_T string="View and filter all my contributions"}" class="{if $cur_route eq "contributions" and $cur_subroute eq "contributions"}active {/if}item">{_T string="My contributions"}</a>
                    <a href="{path_for name="contributions" data=["type" => "transactions"]}" title="{_T string="View and filter all my transactions"}" class="{if $cur_route eq "contributions" and $cur_subroute eq "transactions"}active {/if}item">{_T string="My transactions"}</a>
        {/if}
    {/if}
    {if not $login->isSuperAdmin()}
                    <a href="{path_for name="me"}" title="{_T string="View my member card"}" class="{if $cur_route eq "me" or $cur_route eq "member"}active {/if}item">{_T string="My information"}</a>
    {/if}
                </div>
            </div>
    {if $preferences->showPublicPages($login) eq true}
            <div class="item" title="{_T string="Public pages"}">
                <div class="image header title">
                    <i class="icon eye outline" aria-hidden="true"></i>
                    {_T string="Public pages"}
                </div>
                <div class="menu">
                    <a href="{path_for name="publicList" data=["type" => "list"]}" title="{_T string="Members list"}" class="{if $cur_route eq "publicList" and $cur_subroute eq "list"}active {/if}item">
                        {_T string="Members list"}
                        <i class="icon address book" aria-hidden="true"></i>
                    </a>
                    <a href="{path_for name="publicList" data=["type" => "trombi"]}" title="{_T string="Trombinoscope"}" class="{if $cur_route eq "publicList" and $cur_subroute eq "trombi"}active {/if}item">
                        {_T string="Trombinoscope"}
                        <i class="icon user friends" aria-hidden="true"></i>
                    </a>
                    {* Include plugins menu entries *}
                    {$plugins->getPublicMenus($tpl, true)}
                </div>
            </div>
    {/if}
    {if $login->isAdmin()}
            <div class="item" title="{_T string="Configuration"}">
                <div class="image header title">
                    <i class="icon tools" aria-hidden="true"></i>
                    {_T string="Configuration"}
                </div>
                <div class="menu">
                        <a href="{path_for name="preferences"}" title="{_T string="Set applications preferences (address, website, member's cards configuration, ...)"}" class="{if $cur_route eq "preferences"}active {/if}item">{_T string="Settings"}</a>
                        <a href="{path_for name="plugins"}" title="{_T string="Informations about available plugins"}" class="{if $cur_route eq "plugins"}active {/if}item">{_T string="Plugins"}</a>
                        <a href="{path_for name="configureCoreFields"}" title="{_T string="Customize fields order, set which are required, and for who they're visibles"}" class="{if $cur_route eq "configureCoreFields"}active {/if}item">{_T string="Core fields"}</a>
                        <a href="{path_for name="configureDynamicFields"}" title="{_T string="Manage additional fields for various forms"}" class="{if $cur_route eq "configureDynamicFields" or $cur_route eq 'editDynamicField'}active {/if}item">{_T string="Dynamic fields"}</a>
                        <a href="{path_for name="dynamicTranslations"}" title="{_T string="Translate additionnals fields labels"}" class="{if $cur_route eq "dynamicTranslations"}active {/if}item">{_T string="Translate labels"}</a>
                        <a href="{path_for name="entitleds" data=["class" => "status"]}" title="{_T string="Manage statuses"}" class="{if $cur_route eq "entitleds" and $cur_subroute eq "status"}active {/if}item">{_T string="Manage statuses"}</a>
                        <a href="{path_for name="entitleds" data=["class" => "contributions-types"]}" title="{_T string="Manage contributions types"}" class="{if $cur_route eq "entitleds" and $cur_subroute eq "contributions-types"}active {/if}item">{_T string="Contributions types"}</a>
                        <a href="{path_for name="texts"}" title="{_T string="Manage emails texts and subjects"}" class="{if $cur_route eq "texts"}active {/if}item">{_T string="Emails content"}</a>
                        <a href="{path_for name="titles"}" title="{_T string="Manage titles"}" class="{if $cur_route eq "titles"}active {/if}item">{_T string="Titles"}</a>
                        <a href="{path_for name="pdfModels"}" title="{_T string="Manage PDF models"}" class="{if $cur_route eq "pdfModels"}active {/if}item">{_T string="PDF models"}</a>
                        <a href="{path_for name="paymentTypes"}" title="{_T string="Manage payment types"}" class="{if $cur_route eq "paymentTypes"}active {/if}item">{_T string="Payment types"}</a>
                        <a href="{path_for name="emptyAdhesionForm"}" title="{_T string="Download empty adhesion form"}" class="item">{_T string="Empty adhesion form"}</a>
        {if $login->isSuperAdmin()}
                        <a href="{path_for name="fakeData"}" title="{_T string="Generate fake data"}" class="{if $cur_route eq "fakeData"}active {/if}item">{_T string="Generate fake data"}</a>
                        <a href="{path_for name="adminTools"}" title="{_T string="Various administrative tools"}" class="{if $cur_route eq "adminTools"}active {/if}item">{_T string="Admin tools"}</a>
        {/if}
                </div>
                {* Include plugins menu entries *}
                {$plugins->getMenus($tpl)}
            </div>
    {/if}
{else}
    {if $cur_route neq "login"}
            <a href="{path_for name="slash"}"
               title="{if $login->isLogged()}{_T string="Dashboard"}{else}{_T string="Go back to Galette homepage"}{/if}"
               class="{if $cur_route eq "dashboard" or $cur_route eq "login"}active {/if}item"
            >
                <i class="icon {if $login->islogged()}compass{else}home{/if}" aria-hidden="true"></i>
                {if $login->isLogged()}{_T string="Dashboard"}{else}{_T string="Home"}{/if}
            </a>
    {/if}
    {if $preferences->showPublicPages($login) eq true}
            <a href="{path_for name="publicList" data=["type" => "list"]}" class="{if $cur_route eq "publicList" and $cur_subroute eq "list"}active {/if}item">
                <i class="icon address book" aria-hidden="true"></i>
                {_T string="Members list"}
            </a>
            <a href="{path_for name="publicList" data=["type" => "trombi"]}" class="{if $cur_route eq "publicList" and $cur_subroute eq "trombi"}active {/if}item">
                <i class="icon user friends" aria-hidden="true"></i>
                {_T string="Trombinoscope"}
            </a>
    {/if}
            <div class="item">
    {if $preferences->pref_bool_selfsubscribe eq true and $cur_route neq "subscribe"}
                <a href="{path_for name="subscribe"}" class="button" title="{_T string="Subscribe"}">
                    <i class="icon add user" aria-hidden="true"></i>
                    {_T string="Subscribe"}
                </a>
    {/if}
    {if $cur_route neq "login"}
                <a href="{path_for name="slash"}" class="button" title="{_T string="Login"}">
                    <i class="icon sign in alt" aria-hidden="true"></i>
                    {_T string="Login"}
                </a>
    {/if}
            </div>
{/if}
            <div class="language item" title="{_T string="Choose your language"}">
                <i class="icon language" aria-hidden="true"></i>
                <span>{$galette_lang}</span>
                <div class="menu">
    {foreach item=langue from=$languages}
        {if $langue->getAbbrev() neq $galette_lang}
                    <a href="?pref_lang={$langue->getID()}" class="item">
                        {$langue->getName()} <span>({$langue->getAbbrev()})<span>
                    </a>
        {/if}
    {/foreach}
                </div>
            </div>
        </div>
        <header id="top-navbar" class="ui top fixed pointing menu bg-color">
            <div class="ui fluid container">
                <a class="toc item">
                    <i class="sidebar icon"></i>
                </a>
                <a href="{path_for name="slash"}" title="{_T string="Go back to Galette homepage"}" class="header {if $cur_route eq "slash" or $cur_route eq 'login'}active {/if}item">
                    <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" class="logo" />
                    <span>{$preferences->pref_nom}{if $preferences->pref_slogan}<br>{$preferences->pref_slogan}{/if}</span>
                </a>
{if $cur_route neq "login"}
                <a
                    href="{path_for name="slash"}"
                    title="{if $login->isLogged()}{_T string="Dashboard"}{else}{_T string="Go back to Galette homepage"}{/if}"
                    class="{if $cur_route eq "slash" or $cur_route eq 'dashboard'}active {/if}item"
                >
                    <i class="icon {if $login->islogged()}compass{else}home{/if}" aria-hidden="true"></i>
                    {_T string="Home"}
                </a>
{/if}
{if $preferences->showPublicPages($login) eq true}
                <a
                    href="{path_for name="publicList" data=["type" => "list"]}" title="{_T string="Members list"}"
                    class="{if $cur_route eq "publicList" and $cur_subroute eq "list"}active {/if}item"
                >
                    <i class="icon address book" aria-hidden="true"></i>
                    {_T string="Members list"}
                </a>
                <a
                    href="{path_for name="publicList" data=["type" => "trombi"]}" title="{_T string="Trombinoscope"}"
                    class="{if $cur_route eq "publicList" and $cur_subroute eq "trombi"}active {/if}item"
                >
                    <i class="icon user friends" aria-hidden="true"></i>
                    {_T string="Trombinoscope"}
                </a>
                {* Include plugins menu entries *}
                {$plugins->getPublicMenus($tpl, true)}
{/if}
                <div class="right item">
{if !$login->isLogged()}
    {if $preferences->pref_bool_selfsubscribe eq true and $cur_route neq "subscribe"}
                    <a
                        href="{path_for name="subscribe"}"
                        class="button"
                        title="{_T string="Subscribe"}"
                    >
                        <i class="icon add user" aria-hidden="true"></i>
                        {_T string="Subscribe"}
                    </a>
    {/if}
    {if $cur_route neq "login"}
                    <a
                        href="{path_for name="slash"}"
                        class="button"
                        title="{_T string="Login"}"
                    >
                        <i class="icon sign in alt" aria-hidden="true"></i>
                        {_T string="Login"}
                    </a>
    {/if}
{else}
                    <a class="button" title="{_T string="View your member card"}" href="{if $login->isSuperAdmin()}{path_for name="slash"}{else}{path_for name="me"}{/if}">{$login->loggedInAs(true)}</a>
                    <a class="button" href="{if $login->isImpersonated()}{path_for name="unimpersonate"}{else}{path_for name="logout"}{/if}"><i class="icon {if $login->isImpersonated()}user secret{else}sign out alt{/if}"></i><span class="sr-only">{_T string="Log off"}</span></a>
{/if}
                </div>
                <div class="language ui simple dropdown item">
                    <i class="icon language" aria-hidden="true"></i>
                    <span>{$galette_lang}</span>
                    <i class="icon dropdown" aria-hidden="true"></i>
                    <div class="menu">
{foreach item=langue from=$languages}
    {if $langue->getAbbrev() neq $galette_lang}
                        <a href="?pref_lang={$langue->getID()}" title="{_T string="Switch locale to '%locale'" pattern="/%locale/" replace=$langue->getName()}" class="item">
                            {$langue->getName()} <span>({$langue->getAbbrev()})<span>
                        </a>
    {/if}
{/foreach}
                    </div>
                </div>
        </header>
        <div class="pusher">
            <div id="main" class="ui main column horizontally padded grid">
                <aside class="three wide computer only column">
                    <div id="menu">
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
                            <li{if $cur_route eq "dashboard"} class="selected"{/if}><a href="{path_for name="dashboard"}" title="{_T string="Go to Galette's dashboard"}">{_T string="Dashboard"}</a></li>
  {if $login->isAdmin() or $login->isStaff() or $login->isGroupManager()}
                            <li{if $cur_route eq "members"} class="selected"{/if}><a href="{path_for name="members"}" title="{_T string="View, search into and filter member's list"}">{_T string="List of members"}</a></li>
                            <li{if $cur_route eq "advanced-search"} class="selected"{/if}><a href="{path_for name="advanced-search"}" title="{_T string="Perform advanced search into members list"}">{_T string="Advanced search"}</a></li>
                            <li{if $cur_route eq "searches"} class="selected"{/if}><a href="{path_for name="searches"}" title="{_T string="Saved searches"}">{_T string="Saved searches"}</a></li>
                            <li{if $cur_route eq "groups"} class="selected"{/if}><a href="{path_for name="groups"}" title="{_T string="View and manage groups"}">{_T string="Manage groups"}</a></li>
  {/if}
  {if $login->isAdmin() or $login->isStaff()}
                            <li{if $cur_route eq "contributions" and $cur_subroute eq "contributions"} class="selected"{/if}><a href="{path_for name="contributions" data=["type" => "contributions"]}" title="{_T string="View and filter contributions"}">{_T string="List of contributions"}</a></li>
                            <li{if $cur_route eq "contributions" and $cur_subroute eq "transactions"} class="selected"{/if}><a href="{path_for name="contributions" data=["type" => "transactions"]}" title="{_T string="View and filter transactions"}">{_T string="List of transactions"}</a></li>
                            <li{if $cur_route eq "editmember"} class="selected"{/if}><a href="{path_for name="editmember" data=["action" => "add"]}" title="{_T string="Add new member in database"}">{_T string="Add a member"}</a></li>
                            <li{if $cur_route eq "contribution" and $cur_subroute eq "fee"} class="selected"{/if}><a href="{path_for name="contribution" data=["type" => "fee", "action" => "add"]}" title="{_T string="Add new membership fee in database"}">{_T string="Add a membership fee"}</a></li>
                            <li{if $cur_route eq "contribution" and $cur_subroute eq "donation"} class="selected"{/if}><a href="{path_for name="contribution" data=["type" => "donation", "action" => "add"]}" title="{_T string="Add new donation in database"}">{_T string="Add a donation"}</a></li>
                            <li{if $cur_route eq "transaction"} class="selected"{/if}><a href="{path_for name="transaction" data=["action" => "add"]}" title="{_T string="Add new transaction in database"}">{_T string="Add a transaction"}</a></li>
                            <li{if $cur_route eq "reminders"} class="selected"{/if}><a href="{path_for name="reminders"}" title="{_T string="Send reminders to late members"}">{_T string="Reminders"}</a></li>
                            <li{if $cur_route eq "history"} class="selected"{/if}><a href="{path_for name="history"}" title="{_T string="View application's logs"}">{_T string="Logs"}</a></li>
                            <li{if $cur_route eq "mailings"} class="selected"{/if}><a href="{path_for name="mailings"}" title="{_T string="Manage mailings that has been sent"}">{_T string="Manage mailings"}</a></li>
                            <li{if $cur_route eq "export"} class="selected"{/if}><a href="{path_for name="export"}" title="{_T string="Export some data in various formats"}">{_T string="Exports"}</a></li>
                            <li{if $cur_route eq "import" or $cur_route eq "importModel"} class="selected"{/if}><a href="{path_for name="import"}" title="{_T string="Import members from CSV files"}">{_T string="Imports"}</a></li>
                            <li class="mnu_last{if $cur_route eq "charts"} selected{/if}"><a href="{path_for name="charts"}" title="{_T string="Various charts"}">{_T string="Charts"}</a></li>
  {else}
                            <li{if $cur_route eq "contributions" and $cur_subroute eq "contributions"} class="selected"{/if}><a href="{path_for name="contributions" data=["type" => "contributions"]}" title="{_T string="View and filter all my contributions"}">{_T string="My contributions"}</a></li>
                            <li{if $cur_route eq "contributions" and $cur_subroute eq "transactions"} class="selected"{/if}><a href="{path_for name="contributions" data=["type" => "transactions"]}" title="{_T string="View and filter all my transactions"}">{_T string="My transactions"}</a></li>
  {/if}
  {if !$login->isSuperAdmin()}
                            <li{if $cur_route eq "me" or $cur_route eq "member"} class="selected"{/if}><a href="{path_for name="me"}" title="{_T string="View my member card"}">{_T string="My information"}</a></li>
  {/if}
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
                            <li{if $cur_route eq "entitleds" and $cur_subroute eq "status"} class="selected"{/if}><a href="{path_for name="entitleds" data=["class" => "status"]}" title="{_T string="Manage statuses"}">{_T string="Manage statuses"}</a></li>
                            <li{if $cur_route eq "entitleds" and $cur_subroute eq "contributions-types"} class="selected"{/if}><a href="{path_for name="entitleds" data=["class" => "contributions-types"]}" title="{_T string="Manage contributions types"}">{_T string="Contributions types"}</a></li>
                            <li{if $cur_route eq "texts"} class="selected"{/if}><a href="{path_for name="texts"}" title="{_T string="Manage emails texts and subjects"}">{_T string="Emails content"}</a></li>
                            <li{if $cur_route eq "titles"} class="selected"{/if}><a href="{path_for name="titles"}" title="{_T string="Manage titles"}">{_T string="Titles"}</a></li>
                            <li{if $cur_route eq "pdfModels"} class="selected"{/if}><a href="{path_for name="pdfModels"}" title="{_T string="Manage PDF models"}">{_T string="PDF models"}</a></li>
                            <li{if $cur_route eq "paymentTypes"} class="selected"{/if}><a href="{path_for name="paymentTypes"}" title="{_T string="Manage payment types"}">{_T string="Payment types"}</a></li>
                            <li><a href="{path_for name="emptyAdhesionForm"}" title="{_T string="Download empty adhesion form"}">{_T string="Empty adhesion form"}</a></li>
            {if $login->isSuperAdmin()}
                            <li{if $cur_route eq "fakeData"} class="selected"{/if}><a href="{path_for name="fakeData"}">{_T string="Generate fake data"}</a></li>
                            <li{if $cur_route eq "adminTools"} class="selected"{/if}><a href="{path_for name="adminTools"}" title="{_T string="Various administrative tools"}">{_T string="Admin tools"}</a></li>
            {/if}
                        </ul>
{/if}
                        {* Include plugins menu entries *}
                        {$plugins->getMenus($tpl)}
                    </div>
                </aside>
                <section class="ui sixteen wide mobile thirteen wide computer column {if $contentcls}{$contentcls}{/if}">
                    <h1 id="titre">
                        {$page_title}
{if $cur_route neq 'mailing' and $existing_mailing eq true}
                            <a
                                id="recup_mailing"
                                href="{path_for name="mailing"}"
                                class="tooltip"
                                title="{_T string="A mailing exists in the current session. Click here if you want to resume or cancel it."}"
                            >
                                <i class="fas fa-mail-bulk"></i>
                                <span class="sr-only">{_T string="Existing mailing"}</span>
                            </a>
    {/if}
                    </h1>
                    {include file="global_messages.tpl"}
                    {*$content*}
                    {block name="content"}{_T string="Page content"}{/block}
                    {include file="footer.tpl"}
                </section>
            </div>
        </div>
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
