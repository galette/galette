    {* Let's see if there are error messages to show *}
    {assign var="error_detected" value=$flash->getMessage('error_detected')}
    {if is_array($error_detected) && $error_detected|@count > 0}
            <div id="errorbox">
                <h1>{_T string="- ERROR -"}</h1>
                <ul>
        {foreach from=$flash->getMessage('error_detected') item=error}
                    <li>{$error}</li>
        {/foreach}
                </ul>
            </div>
    {/if}

    {* Let's see if there are warning messages to show *}
    {assign var="warning_detected" value=$flash->getMessage('warning_detected')}
    {if is_array($warning_detected) && $warning_detected|@count > 0}
            <div id="warningbox">
                <h1>{_T string="- WARNING -"}</h1>
                <ul>
        {foreach from=$flash->getMessage('warning_detected') item=warning}
                    <li>{$warning}</li>
        {/foreach}
                </ul>
            </div>
    {/if}

    {* In case of a redirection, we inform the user, and propose a direct link *}
    {if $head_redirect}
        <div id="infobox">
            {_T string="You will be redirected in %timeout seconds. If not, please click on the following link:" pattern="/%timeout/" replace=$head_redirect.timeout}
            <br/><a href="{$head_redirect.url}">{_T string="Do not wait timeout and go to the next page now :)"}</a>
        </div>
    {/if}

    {* Let's see if there are success messages to show *}
    {assign var="success_detected" value=$flash->getMessage('success_detected')}
    {if is_array($success_detected) && $success_detected|@count > 0}
        <div id="successbox">
                <ul>
        {foreach from=$flash->getMessage('success_detected') item=success}
                    <li>{$success}</li>
        {/foreach}
                </ul>
        </div>
    {/if}

    {* Renew telemetry *}
    {if $renew_telemetry}
        {include file="telemetry.tpl" part="dialog"}
        <div id="renewbox">
            {_T string="It's been over a year since you sent Telemetry data."}<br/>
            {_T string="Do you want to send it again?"}<br/>
            <a href="#" id="telemetry">{_T string="Yes"}</a>
            - <a href="#" id="norenew">{_T string="No"}</a>
            - <a href="#" id="renewlater">{_T string="Later"}</a>
        </div>
    {/if}
