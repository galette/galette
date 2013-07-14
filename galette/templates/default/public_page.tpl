<!DOCTYPE html>
<html lang="{$galette_lang}" class="public_page{if $additionnal_html_class} {$additionnal_html_class}{/if}">
    <head>
        {include file='common_header.tpl'}
{if $require_calendar}
        <script type="text/javascript" src="{$jquery_dir}jquery-ui-{$jquery_ui_version}/jquery.ui.datepicker.min.js"></script>
    {if $galette_lang ne 'en'}
        <script type="text/javascript" src="{$jquery_dir}jquery-ui-{$jquery_ui_version}/i18n/jquery.ui.datepicker-{$galette_lang}.min.js"></script>
    {/if}
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
        <header>
            <img src="{$galette_base_path}picture.php?logo=true" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" />
            <ul id="langs">
{foreach item=langue from=$languages}
                <li><a href="?pref_lang={$langue->getID()}"><img src="{$langue->getFlag()}" alt="{$langue->getName()}" lang="{$langue->getAbbrev()}" class="flag"/></a></li>
{/foreach}
            </ul>
{if $login->isLogged()}
            <div id="user">
                <a id="userlink" title="{_T string="View your member card"}" href="{$galette_base_path}voir_adherent.php">{$login->loggedInAs(true)}</a>
                <a id="logout" title="{_T string="Log off"}" href="{$galette_base_path}index.php?logout=1">{_T string="Log off"}</a>
            </div>
{/if}
{if $GALETTE_MODE eq 'DEMO'}
        <div id="demo" title="{_T string="This application runs under DEMO mode, all features may not be available."}">
            {_T string="Demonstration"}
        </div>
{/if}
        </header>
        <h1 id="titre">{$page_title}</h1>
        <p id="asso_name">{$preferences->pref_nom}{if $preferences->pref_slogan}&nbsp;: {$preferences->pref_slogan}{/if}</p>
        <nav>
            <a id="backhome" class="button{if $PAGENAME eq "index.php"} selected{/if}" href="{$galette_base_path}index.php">{_T string="Home"}</a>
    {if !$login->isLogged()}
        {if $preferences->pref_bool_selfsubscribe eq true}
            <a id="subscribe" class="button{if $PAGENAME eq "self_adherent.php"} selected{/if}" href="{$galette_base_path}self_adherent.php">{_T string="Subscribe"}</a>
        {/if}
        {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED')}
            <a id="lostpassword" class="button{if $PAGENAME eq "lostpasswd.php"} selected{/if}" href="{$galette_base_path}lostpasswd.php">{_T string="Lost your password?"}</a>
        {/if}
    {/if}
    {if $preferences->showPublicPages() eq true}
            <a id="memberslist" class="button{if $PAGENAME eq "liste_membres.php"} selected{/if}" href="{$galette_base_path}public/liste_membres.php" title="{_T string="Members list"}">{_T string="Members list"}</a>
            <a id="trombino" class="button{if $PAGENAME eq "trombinoscope.php"} selected{/if}" href="{$galette_base_path}public/trombinoscope.php" title="{_T string="Trombinoscope"}">{_T string="Trombinoscope"}</a>
    {/if}
        </nav>
        {include file="global_messages.tpl"}
        {$content}
        {include file="footer.tpl"}
    </body>
</html>
