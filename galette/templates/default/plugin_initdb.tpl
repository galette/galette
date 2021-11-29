{if $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}
{block name="content"}
<section id="plugin_install">
    <header>
        <h1>{_T string="%plugin plugin installation" pattern="/%plugin/" replace=$plugin.name}</h1>
    </header>
    <div>
    <form action="{path_for name="pluginInitDb" data=["id" => $plugid]}" id="plugins_initdb_form" method="post">
        <h2>{$page_title}</h2>
{if $mode eq 'ajax'}
    {include file="global_messages.tpl"}
{/if}



{if $step == 1}
        <div id="installation_mode">
            <article id="mode_new" class="installation_mode">
                <h3>
                    <input type="radio" name="install_type" value="{Galette\Core\PluginInstall::INSTALL}" checked="checked" id="install"/>
                    <label for="install">{_T string="New installation"}</label>
                </h3>
                <ul>
                    <li>{_T string="you're installing %name for the first time" pattern="/%name/" replace=$plugin.name},</li>
                    <li>{_T string="you wish to erase an older version of of %name without keeping your data" pattern="/%name/" replace=$plugin.name}.</li>
                </ul>
            </article>
    {if isset($update_scripts) and $update_scripts|@count > 0}
            <article id="mode_update" class="installation_mode">
                <h3>
                    <input type="radio" name="install_type" value="{Galette\Core\PluginInstall::UPDATE}" id="update"/>
                    <label for="update">{_T string="Update"}</label>
                </h3>
                <ul>
                <li>{_T string="you already have installed %name, and you want to upgrade to the latest version" pattern="/%name/" replace=$plugin.name}.</li>
                </ul>
                <p id="warningbox">{_T string="Warning: Don't forget to backup your current database."}</span>
            </article>
    {/if}
        </div>
{/if}
{if $step == 'i2' || $step == 'u2'}
    {$results}
{/if}
{if $step == 'u3'}
        <fieldset class="cssform">
            <legend class="ui-state-active ui-corner-top">{_T string="You current %name version is..." pattern="/%name/" replace=$plugin.name}</legend>
            <ul class="leaders">
    {assign var=last value=0.00}
    {foreach from=$update_scripts key=k item=val}
                <li>
                    <span>
                        <label for="upgrade-{$val}">

        {if $last eq 0.00}
            {_T string="older than %version" pattern="/%version/" replace=$val}
        {elseif $last != $val}
            {_T string="comprised between"} {$last} {_T string="and"} {$val}
        {else}
            {$val}
        {/if}
        {assign var=last value=$val}
                        </label>
                    </span>
                    <span>
                        <input type="radio" name="previous_version" value="{$val}" id="upgrade-{$val}" required/>
                    </span>
                </li>
    {/foreach}
            </ul>
        </fieldset>
{/if}
{if $step == 'i4' || $step == 'u4'}
        <p>{_T string="(Errors on DROP and RENAME operations can be ignored)"}</p>
    {if $error_detected|@count > 0}
        <p id="errorbox">
        {if $step == 'i3'}
            {_T string="The tables are not totally created, it may be a permission problem."}
        {else}
            {_T string="The tables have not been totally created, it may be a permission problem."}
            <br/>
            {_T string="Your database is maybe not usable, try to restore the older version."}
        {/if}
        </p>
    {/if}
{/if}
{if $step == 'i5' || $step =='u5'}
        <p>
            {if $step == 'i5'}
                {_T string="Plugin '%name' has been successfully installed!" pattern="/%name/" replace=$plugin.name}
            {else}
                {_T string="Plugin '%name' has been successfully updated!" pattern="/%name/" replace=$plugin.name}
            {/if}
        </p>
{/if}
{if $istep < 5}
        <p id="btn_box">
            <input type="hidden" name="plugid" value="{$plugid}"/>
    {if $istep > 1 && isset($install_type)}
            <input type="hidden" name="install_type" value="{$install_type}"/>
    {/if}
    {if $error_detected|@count == 0 && $istep >= 2 || $istep > 2}
            <input type="hidden" name="install_dbperms_ok" value="1"/>
    {/if}
    {if $error_detected|@count == 0 && $istep >= 4 || $istep > 4}
            <input type="hidden" name="install_dbwrite_ok" value="1"/>
    {/if}
    {if $error_detected|@count > 0}
            <input type="submit" value="{_T string="Retry"}"/>
    {else}
            <button id="next" type="submit">{_T string="Next step"} <i class="fas fa-forward"></i></button>
    {/if}
{else}
    {if $mode eq 'ajax'}
            <a href="#" class="button" id="btnback">{_T string="Close"}</a>
    {else}
            <a href="{path_for name="plugins"}" class="button" id="btnback"><i class="fas fa-backward"></i> {_T string="Back to plugins managment page"}</a>
    {/if}
{/if}
            {include file="forms_types/csrf.tpl"}
        </p>
    </form>
    </div>
    <footer>
        <p>{_T string="Steps:"}</p>
        <ol>
            <li{if $step == 1} class="current"{/if}>{_T string="Installation mode"} - </li>
            <li{if $step == 'i2' || $step == 'u2'} class="current"{/if}>{_T string="Access permissions to database"} - </li>
        {if isset($install_type) and $install_type == 'upgrade'}
            <li{if $step == 'u3'} class="current"{/if}>{_T string="Version selection"} - </li>
            </li>
        {/if}
            <li{if $step == 'i4' || $step == 'u4'} class="current"{/if}>{if !isset($install_type) or $install_type == 'install'}{_T string="Database installation"}{else}{_T string="Database upgrade"}{/if} - </li>
            <li{if $step == 'i5' || $step == 'u5'} class="current"{/if}>{_T string="End!"}</li>
        </ol>
    </footer>
</section>
{/block}
