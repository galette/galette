{if $ajax}
    {include file="global_messages.tpl"}
{/if}
<form action="ajax_plugins_initdb.php" id="plugins_initdb_form" method="post">
    <div id="installpage">
{if $step == 1}
        <p>{_T string="Plugin '%name' requires a database to work. These screens will help you setup that one for the first time." pattern="/%name/" replace=$plugin.name}</p>
        <p id="warningbox">
            {_T string="Warning: Don't forget to backup your current database."}
        </p>
        <p>{_T string="Select installation mode to launch"}</p>
        <p>
            <input type="radio" name="install_type" value="install" checked="checked" id="install"/> <label for="install">{_T string="New installation:"}</label><br />
            {_T string="You're installing '%name' plugin for the first time, or you wish to erase an older version of the plugin without keeping your data" pattern="/%name/" replace=$plugin.name}
        </p>
    {if $update_scripts|@count > 0}
        <p>{_T string="Update"}</p>
        <ul class="list">
        {$last = '0.00'}
        {foreach from=$update_scripts key=k item=val}
            <input type="radio" name="install_type" value="upgrade-{$val}" id="upgrade-{$val}"/>
            <label for="upgrade-{$val}">
            {if $last != $val-0.01|number_format:2}
                {_T string="Your current %name version is comprised between" pattern="/%name/" replace=$plugin.name} {$last} {_T string="and"} {$val-0.01|number_format:2} </label><br />
            {else}
                {_T string="Your current %name version is" pattern="/%name/" replace=$plugin.name} {$val-0.01|number_format:2}</label>
            {/if}
            {$last = $val}
        {/foreach}
        </ul>
    {/if}
{/if}
{if $step == 'i2' || $step == 'u2'}
        <p>
    {if $step == 'i2'}
            {_T string="To run, Galette needs a number of rights on the database (CREATE, DROP, DELETE, UPDATE, SELECT and INSERT)"}
    {/if}
    {if $step == 'u2'}
            {_T string="In order to be updated, Galette needs a number of rights on the database (CREATE, DROP, DELETE, UPDATE, SELECT and INSERT)"}
    {/if}
        </p>
{/if}
{if $step == 'i3' || $step == 'u3'}
        <p>{_T string="(Errors on DROP and RENAME operations can be ignored)"}</p>
    {if $eror_detected|@count > 0}
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
{if $step == 'i4' || $step =='u4'}
        <p>
            {if $step == 'i4'}
                {_T string="Plugin '%name' has been successfully installed!" pattern="/%name/" replace=$plugin.name}
            {else}
                {_T string="Plugin '%name' has been successfully updated!" pattern="/%name/" replace=$plugin.name}
            {/if}
        </p>
{/if}
{if $istep < 4}
        <p id="submit_btn">
            <input type="hidden" name="plugid" value="{$plugid}"/>
    {if $istep > 1}
            <input type="hidden" name="install_type" value="{$install_type}"/>
    {/if}
    {if $error_detected|@count == 0 && $istep >= 2 || $istep > 2}
            <input type="hidden" name="install_permsok" value="1"/>
    {/if}
    {if $error_detected|@count == 0 && $istep >= 3 || $istep > 3}
            <input type="hidden" name="install_dbwrite_ok" value="1"/>
    {/if}
    {if $error_detected|@count > 0}
            <input type="submit" value="{_T string="Retry"}"/>
    {else}
            <input type="submit" value="{_T string="Next step"}"/>
    {/if}
{else}
    {if $ajax}
            <a href="#" class="button" id="btnback">{_T string="Close"}</a>
    {else}
            <a href="plugins.php" class="button" id="btnback">{_T string="Back to plugins managment page"}</a>
    {/if}
{/if}
        </p>
    </div>
</form>
<div id="footerinstall">
    <p>{_T string="Steps:"}</p>
    <ol>
        <li{if $step == 1} class="current"{/if}>{_T string="Installation mode"} - </li>
        <li{if $step == 'i2' || $step == 'u2'} class="current"{/if}>{_T string="Access permissions to database"} - </li>
        <li{if $step == 'i3' || $step == 'u3'} class="current"{/if}>{_T string="Tables Creation/Update"} - </li>
        <li{if $step == 'i4' || $step == 'u4'} class="current"{/if}>{_T string="End!"}</li>
    </ol>
</div>
