{* Let's see if there are error messages to show *}
{assign var="errors" value=$flash->getMessage('error_detected')}
{if isset($error_detected) && is_array($error_detected)}
    {foreach from=$error_detected item=e}
        {$errors[] = $e}
    {/foreach}
{/if}
{if is_array($errors) && $errors|@count > 0}
    <div class="ui negative message">
        <i class="close icon"></i>
        <div class="header">{_T string="- ERROR -"}</div>
        <ul>
            {foreach from=$errors item=error}
                <li>{$error}</li>
            {/foreach}
        </ul>
    </div>
{/if}


{* Let's see if there are warning messages to show *}
{assign var="warnings" value=$flash->getMessage('warning_detected')}
{if isset($warning_detected) && is_array($warning_detected)}
    {foreach from=$warning_detected item=w}
        {$warnings[] = $w}
    {/foreach}
{/if}
{if is_array($warnings) && $warnings|@count > 0}
    {foreach from=$warnings item=warning}
        <div class="ui warning message">
            <i class="close icon"></i>
            <div class="header">{_T string="- WARNING -"}</div>
            <ul>
                {foreach from=$warnings item=warning}
                    <li>{$warning}</li>
                {/foreach}
            </ul>
        </div>
    {/foreach}
{/if}

{* Let's see if there are success messages to show *}
{assign var="successs" value=$flash->getMessage('success_detected')}
{if isset($success_detected) && is_array($success_detected)}
    {foreach from=$success_detected item=s}
        {$successs[] = $s}
    {/foreach}
{/if}
{if is_array($successs) && $successs|@count > 0}
    {foreach from=$successs item=success}
        <div class="ui positive message">
            <i class="close icon"></i>
            <ul>
                {foreach from=$success item=s}
                    <li>{$s}</li>
                {/foreach}
            </ul>
        </div>
    {/foreach}
{/if}

{* Renew telemetry *}
{if isset($renew_telemetry) && $renew_telemetry}
    {include file="telemetry.tpl" part="dialog"}
    <div class="ui blue message" id="renewbox">
        {_T string="Your telemetry data are more than one year old."}<br/>
        {_T string="Do you want to send it again?"}<br/>
        <a href="#" id="telemetry">{_T string="Yes"}</a>
        - <a href="#" id="norenew">{_T string="No"}</a>
        - <a href="#" id="renewlater">{_T string="Later"}</a>
    </div>
{/if}