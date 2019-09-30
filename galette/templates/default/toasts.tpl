        <script type="text/javascript">

    {* Let's see if there are error messages to show *}
    {assign var="errors" value=$flash->getMessage('error_detected')}
    {if isset($error_detected) && is_array($error_detected)}
        {foreach from=$error_detected item=e}
            {$errors[] = $e}
        {/foreach}
    {/if}
    {if is_array($errors) && $errors|@count > 0}
        {foreach from=$errors item=error}
            var error_msg = '<li>{$error}</li>';
        {/foreach}
            $('#main')
                .toast({
                    displayTime: 0,
                    closeIcon: true,
                    title: '{_T string="- ERROR -"}',
                    message: '<ul>' + error_msg + '</ul>',
                    class: 'red'
                })
            ;
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
            var warning_msg = '<li>{$warning}</li>';
        {/foreach}
            $('#main')
                .toast({
                    displayTime: 0,
                    closeIcon: true,
                    title: '{_T string="- WARNING -"}',
                    message: '<ul>' + warning_msg + '</ul>',
                    class: 'orange'
                })
            ;
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
            var success_msg = '<li>{$success}</li>';
        {/foreach}
            $('#main')
                .toast({
                    message: '<ul>' + success_msg + '</ul>',
                    class: 'green'
                })
            ;
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

        </script>
