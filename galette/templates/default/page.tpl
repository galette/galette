<!DOCTYPE html>
<html lang="{$galette_lang}">
    <head>
        {include file='common_header.tpl'}
{if $color_picker}
        <script type="text/javascript" src="{$jquery_dir}farbtastic.js"></script>
        <link rel="stylesheet" type="text/css" href="{$template_subdir}farbtastic.css"/>
{/if}
{* JQuery UI related *}
{if $require_sorter or $require_dialog}
        <script type="text/javascript" src="{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.mouse.min.js"></script>
{/if}
{if $require_sorter}
        <script type="text/javascript" src="{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.sortable.min.js"></script>
{/if}
{if $require_calendar}
        <script type="text/javascript" src="{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.datepicker.min.js"></script>
    {if $galette_lang ne 'en'}
        <script type="text/javascript" src="{$jquery_dir}jquery-ui-{$jquery_ui_version}/i18n/jquery.ui.datepicker-{$galette_lang}.min.js"></script>
    {/if}
{/if}
{if $require_tabs}
        <script type="text/javascript" src="{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.tabs.min.js"></script>
{/if}
{if $require_dialog}
        {* Drag component, only used for Dialog for the moment *}
        <script type="text/javascript" src="{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.draggable.min.js"></script>
        {* So the dialog could be aligned in the middle of the screen *}
        <script type="text/javascript" src="{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.position.min.js"></script>
        <script type="text/javascript" src="{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.dialog.min.js"></script>
{/if}
{* /JQuery UI related *}
{if $require_cookie}
        <script type="text/javascript" src="{$jquery_dir}jquery.cookie.js"></script>
{/if}
{if $require_charts}
        <link rel="stylesheet" type="text/css" href="{$jquery_dir}jqplot-{$jquery_jqplot_version}/jquery.jqplot.css" />
        <script type="text/javascript" src="{$jquery_dir}jqplot-{$jquery_jqplot_version}/jquery.jqplot.min.js"></script>
        <script type="text/javascript" src="{$jquery_dir}jqplot-{$jquery_jqplot_version}/plugins/jqplot.pieRenderer.min.js"></script>
        <script type="text/javascript" src="{$jquery_dir}jqplot-{$jquery_jqplot_version}/plugins/jqplot.barRenderer.min.js"></script>
        <script type="text/javascript" src="{$jquery_dir}jqplot-{$jquery_jqplot_version}/plugins/jqplot.pointLabels.min.js"></script>
        <script type="text/javascript" src="{$jquery_dir}jqplot-{$jquery_jqplot_version}/plugins/jqplot.categoryAxisRenderer.min.js"></script>
{/if}
{if $html_editor}
    {if !isset($plugged_html_editor)}
        <script type="text/javascript" src="{$jquery_dir}markitup-{$jquery_markitup_version}/jquery.markitup.js"></script>
        <script type="text/javascript" src="{$jquery_dir}markitup-{$jquery_markitup_version}/sets/html/set-{$galette_lang}.js"></script>
        <link rel="stylesheet" type="text/css" href="{$jquery_dir}markitup-{$jquery_markitup_version}/skins/galette/style.css" />
        <link rel="stylesheet" type="text/css" href="{$jquery_dir}markitup-{$jquery_markitup_version}/sets/html/style.css" />
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
    <script type="text/javascript" src="{$jquery_dir}jquery.jstree.js"></script>
{/if}
{* If some additionnals headers should be added from plugins, we load the relevant template file
We have to use a template file, so Smarty will do its work (like replacing variables). *}
{if $headers|@count != 0}
    {foreach from=$headers item=header}
        {include file=$header}
    {/foreach}
{/if}
{if $head_redirect}
    <meta http-equiv="refresh" content="{$head_redirect.timeout};url={$head_redirect.url}" />
{/if}
</head>
<body>
        {* IE7 and above are no longer supported *}
        <!--[if lt IE 8]>
        <div id="oldie">
            <p>{_T string="Your browser version is way too old and no longer supported in Galette for a while."}</p>
            <p>{_T string="Please update your browser or use an alternative one, like Mozilla Firefox (http://mozilla.org)."}</p>
        </div>
        <![endif]-->
    <div id="menu">
        <div id="logo">
            <img src="{$galette_base_path}picture.php?logo=true" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" />
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
            <li{if $PAGENAME eq "desktop.php"} class="selected"{/if}><a href="{$galette_base_path}desktop.php" title="{_T string="Go to Galette's dashboard"}">{_T string="Dashboard"}</a></li>
            <li{if $PAGENAME eq "gestion_adherents.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_adherents.php" title="{_T string="View, search into and filter member's list"}">{_T string="List of members"}</a></li>
            <li{if $PAGENAME eq "advanced_search.php"} class="selected"{/if}><a href="{$galette_base_path}advanced_search.php" title="{_T string="Perform advanced search into members list"}">{_T string="Advanced search"}</a></li>
            <li{if $PAGENAME eq "gestion_groupes.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_groupes.php" title="{_T string="View and manage groups"}">{_T string="Manage groups"}</a></li>
  {/if}
  {if $login->isAdmin() or $login->isStaff()}
            <li{if $PAGENAME eq "gestion_contributions.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_contributions.php?id_adh=all" title="{_T string="View and filter contributions"}">{_T string="List of contributions"}</a></li>
            <li{if $PAGENAME eq "gestion_transactions.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_transactions.php" title="{_T string="View and filter transactions"}">{_T string="List of transactions"}</a></li>
            <li{if $PAGENAME eq "ajouter_adherent.php"} class="selected"{/if}><a href="{$galette_base_path}ajouter_adherent.php" title="{_T string="Add new member in database"}">{_T string="Add a member"}</a></li>
            <li{if $PAGENAME eq "ajouter_contribution.php"} class="selected"{/if}><a href="{$galette_base_path}ajouter_contribution.php" title="{_T string="Add new contribution in database"}">{_T string="Add a contribution"}</a></li>
            <li{if $PAGENAME eq "ajouter_transaction.php"} class="selected"{/if}><a href="{$galette_base_path}ajouter_transaction.php" title="{_T string="Add new transaction in database"}">{_T string="Add a transaction"}</a></li>
            <li{if $PAGENAME eq "reminder.php"} class="selected"{/if}><a href="{$galette_base_path}reminder.php" title="{_T string="Send reminders to late members"}">{_T string="Reminders"}</a></li>
            <li{if $PAGENAME eq "history.php"} class="selected"{/if}><a href="{$galette_base_path}history.php" title="{_T string="View application's logs"}">{_T string="Logs"}</a></li>
            <li{if $PAGENAME eq "gestion_mailings.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_mailings.php" title="{_T string="Manage mailings that has been sent"}">{_T string="Manage mailings"}</a></li>
            <li{if $PAGENAME eq "export.php"} class="selected"{/if}><a href="{$galette_base_path}export.php" title="{_T string="Export some data in various formats"}">{_T string="Exports"}</a></li>
            <li{if $PAGENAME eq "import.php" or $PAGENAME eq "import_model.php"} class="selected"{/if}><a href="{$galette_base_path}import.php" title="{_T string="Import members from CSV files"}">{_T string="Imports"}</a></li>
            <li class="mnu_last{if $PAGENAME eq "charts.php"} selected{/if}"><a href="{$galette_base_path}charts.php" title="{_T string="Various charts"}">{_T string="Charts"}</a></li>
  {else}
            <li{if $PAGENAME eq "gestion_contributions.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_contributions.php" title="{_T string="View and filter all my contributions"}">{_T string="My contributions"}</a></li>
            <li{if $PAGENAME eq "gestion_transactions.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_transactions.php" title="{_T string="View and filter all my transactions"}">{_T string="My transactions"}</a></li>
  {/if}
  {if !$login->isSuperAdmin()}
            <li{if $PAGENAME eq "voir_adherent.php"} class="selected"{/if}><a href="{$galette_base_path}voir_adherent.php" title="{_T string="View my member card"}">{_T string="My information"}</a></li>
  {/if}
        </ul>
{/if}
{if $preferences->showPublicPages() eq true}
        <h1 class="nojs">{_T string="Public pages"}</h1>
        <ul>
            <li><a href="{$galette_base_path}public/liste_membres.php" title="{_T string="Members list"}">{_T string="Members list"}</a></li>
            <li><a href="{$galette_base_path}public/trombinoscope.php" title="{_T string="Trombinoscope"}">{_T string="Trombinoscope"}</a></li>
        </ul>
{/if}
{if $login->isAdmin()}
        <h1 class="nojs">{_T string="Configuration"}</h1>
        <ul>
            <li{if $PAGENAME eq "preferences.php"} class="selected"{/if}><a href="{$galette_base_path}preferences.php" title="{_T string="Set applications preferences (adress, website, member's cards configuration, ...)"}">{_T string="Settings"}</a></li>
            <li{if $PAGENAME eq "plugins.php"} class="selected"{/if}><a href="{$galette_base_path}plugins.php" title="{_T string="Informations about available plugins"}">{_T string="Plugins"}</a></li>
            <li{if $PAGENAME eq "config_fields.php"} class="selected"{/if}><a href="{$galette_base_path}config_fields.php" title="{_T string="Customize fields order, set which are required, and for who they're visibles"}">{_T string="Customize fields"}</a></li>
            <li{if $PAGENAME eq "configurer_fiches.php" or $PAGENAME eq "editer_champ.php"} class="selected"{/if}><a href="{$galette_base_path}configurer_fiches.php" title="{_T string="Manage additional fields for various forms"}">{_T string="Configure member forms"}</a></li>
            <li{if $PAGENAME eq "traduire_libelles.php"} class="selected"{/if}><a href="{$galette_base_path}traduire_libelles.php" title="{_T string="Translate additionnals fields labels"}">{_T string="Translate labels"}</a></li>
            <li{if $PAGENAME eq "gestion_intitules.php" and $class eq 'Status'} class="selected"{/if}><a href="{$galette_base_path}gestion_intitules.php?class=Status" title="{_T string="Manage statuses"}">{_T string="Manage statuses"}</a></li>
            <li{if $PAGENAME eq "gestion_intitules.php" and $class eq 'ContributionsTypes'} class="selected"{/if}><a href="{$galette_base_path}gestion_intitules.php?class=ContributionsTypes" title="{_T string="Manage contributions types"}">{_T string="Manage contributions types"}</a></li>
            <li{if $PAGENAME eq "gestion_textes.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_textes.php" title="{_T string="Manage emails texts and subjects"}">{_T string="Emails content"}</a></li>
            <li{if $PAGENAME eq "gestion_titres.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_titres.php" title="{_T string="Manage titles"}">{_T string="Titles"}</a></li>
            <li{if $PAGENAME eq "gestion_pdf.php"} class="selected"{/if}><a href="{$galette_base_path}gestion_pdf.php" title="{_T string="Manage PDF models"}">{_T string="PDF models"}</a></li>
        </ul>
{/if}

{* Include plugins menu entries *}
{$plugins->getMenus($tpl, $preferences)}

        <ul id="langs">
{foreach item=langue from=$languages}
            <li><a href="?pref_lang={$langue->getID()}"><img src="{$langue->getFlag()}" alt="{$langue->getName()}" lang="{$langue->getAbbrev()}" class="flag"/></a></li>
{/foreach}
        </ul>
{if $login->isLogged()}
        <div>{$login->loggedInAs()}</div>
        <a id="logout" class="button" href="{$galette_base_path}index.php?logout=1">{_T string="Log off"}</a>
{/if}
    </div>
    <div id="content"{if $contentcls} class="{$contentcls}"{/if}>
        <h1 id="titre">
            {$page_title}
            {if $PAGENAME neq 'mailing_adherents.php' and $existing_mailing eq true}
                <a class="button" id="sendmail" href="{$galette_base_path}mailing_adherents.php" title="{_T string="A mailing exists in the current session. Click here if you want to resume or cancel it."}">
                    {_T string="Existing mailing"}
                </a>
            {/if}
        </h1>
        <p id="asso_name">{$preferences->pref_nom}{if $preferences->pref_slogan}&nbsp;: {$preferences->pref_slogan}{/if}</p>

    {include file="global_messages.tpl"}
        {$content}
    </div>
    {include file="footer.tpl"}
</body>
</html>
