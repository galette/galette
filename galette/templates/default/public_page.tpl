<!DOCTYPE html>
<html lang="{$galette_lang}" class="public_page">
	<head>
		{include file='common_header.tpl'}
{if $require_calendar}
		<script type="text/javascript" src="{$jquery_dir}jquery.ui-{$jquery_ui_version}/jquery.ui.datepicker.min.js"></script>
	{if $lang ne 'en'}
		<script type="text/javascript" src="{$jquery_dir}jquery.ui-{$jquery_ui_version}/i18n/jquery.ui.datepicker-{$galette_lang}.js"></script>
	{/if}
{/if}
        {if $head_redirect}{$head_redirect}{/if}
	</head>
	<body>
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
        </header>
		<h1 id="titre">{$page_title}</h1>
        <p id="asso_name">{$preferences->pref_nom}{if $preferences->pref_slogan}&nbsp;: {$preferences->pref_slogan}{/if}</p>
        <nav>
            <a id="backhome" class="button{if $PAGENAME eq "index.php"} selected{/if}" href="{$galette_base_path}index.php">{_T string="Home"}</a>
    {if !$login->isLogged()}
        {if $preferences->pref_bool_selfsubscribe eq true}
            <a id="subscribe" class="button{if $PAGENAME eq "self_adherent.php"} selected{/if}" href="{$galette_base_path}self_adherent.php">{_T string="Subscribe"}</a>
        {/if}
            <a id="lostpassword" class="button{if $PAGENAME eq "lostpasswd.php"} selected{/if}" href="{$galette_base_path}lostpasswd.php">{_T string="Lost your password?"}</a>
    {/if}
    {if $preferences->showPublicPages() eq true}
            <a id="memberslist" class="button{if $PAGENAME eq "liste_membres.php"} selected{/if}" href="{$galette_base_path}public/liste_membres.php" title="{_T string="Members list"}">{_T string="Members list"}</a>
            <a id="trombino" class="button{if $PAGENAME eq "trombinoscope.php"} selected{/if}" href="{$galette_base_path}public/trombinoscope.php" title="{_T string="Trombinoscope"}">{_T string="Trombinoscope"}</a>
    {/if}
        </nav>

{* Let's see if there are error messages to show *}
{if $error_detected|@count != 0}
				<div id="errorbox">
					<h1>{_T string="- ERROR -"}</h1>
					<ul>
{foreach from=$error_detected item=error}
						<li>{$error}</li>
{/foreach}
					</ul>
				</div>
{/if}

{* Let's see if there are warning messages to show *}
{if $warning_detected|@count != 0}
				<div id="warningbox">
					<h1>{_T string="- WARNING -"}</h1>
					<ul>
{foreach from=$warning_detected item=warning}
						<li>{$warning}</li>
{/foreach}
					</ul>
				</div>
{/if}

{* Let's see if there are success messages to show *}
{if $success_detected|@count > 0}
    <div id="successbox">
            <ul>
    {foreach from=$success_detected item=success}
                <li>{$success}</li>
    {/foreach}
            </ul>
    </div>
{/if}

        {$content}
        {include file="footer.tpl"}
	</body>
</html>
