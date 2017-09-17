{if $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}
{block name="content"}
    <div id="confirm_removal"{if $mode neq 'ajax'} class="center"{else} title="{$page_title}"{/if}>
    <form action="{$form_url}" method="post">
        {if $mode neq 'ajax'}<h2>{$page_title}</h2>{/if}
        {if isset($with_cascade)}
            <p>
                <label for="cascade">{_T string="Cascade delete"}</label>
                <input type="checkbox" name="cascade" id="cascade" value="true" title="{_T string="Delete all associated data"}"/>
            </p>
        {/if}
        {if isset($message)}<p>{$message}</p>{/if}
        <p>{_T string="Are you sure you want to proceed?"}<br/>{_T string="This can't be undone."}</p>
        <div class="button-container">
            <input type="submit" id="delete" value="{_T string="Remove"}"/>
            <a href="{$cancel_uri}" class="button" id="btncancel">{_T string="Cancel"}</a>
            <input type="hidden" name="confirm" value="1"/>
            {if $mode eq 'ajax'}<input type="hidden" name="ajax" value="true"/>{/if}
            {foreach $data as $key=>$value}
                {if is_array($value)}
                    {foreach $value as $val}
                <input type="hidden" name="{$key}[]" value="{$val}"/>
                    {/foreach}
                {else}
                <input type="hidden" name="{$key}" value="{$value}"/>
                {/if}
            {/foreach}
        </div>
    </form>
    </div>
{/block}

{block name="javascripts"}
{/block}
