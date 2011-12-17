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

    {* In case of a redirection, we inform the user, and propose a direct link *}
    {if $head_redirect}
        <div id="infobox">
            {_T string="You will be redirected in %timeout seconds. If not, please click on the following link:" pattern="/%timeout/" replace=$head_redirect.timeout}
            <br/><a href="{$head_redirect.url}">{_T string="Do not wait timeout and go to the next page now :)"}</a>
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
