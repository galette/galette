<form action="ajax_plugins_initdb.php" method="post">
    <div id="installpage">
{if $step == 1}
        <h2>{_T string="Installation mode"}</h2>
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
        <p id="submit_btn">
            <input type="submit" value="{_T string="Next step"}"/>
        </p>
    </div>
</form>
<div id="footerinstall">
    <p>{_T string="Steps:"}</p>
    <ol>
        <li{if $step == 1} class="current"{/if}>{_T string="Installation mode"} - </li>
        <li{if $step == 2} class="current"{/if}>{_T string="Installation mode"} - </li>
        <li{if $step == 'i3' || $step == 'u3'} class="current"{/if}>{_T string="Access permissions to database"} - </li>
        <li{if $step == 'i4' || $step == 'u4'} class="current"{/if}>{_T string="End!"}</li>
    </ol>
</div>
