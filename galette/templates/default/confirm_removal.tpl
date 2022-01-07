{if $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}
{block name="content"}
    <div id="confirm_removal"{if $mode neq 'ajax'} class="center"{else} title="{$page_title}"{/if}>
    <form action="{$form_url}" method="post">
    {if $mode neq 'ajax'}
        <div class="ui segment">
            <div class="content">
        {if isset($with_cascade)}
                <div class="field inline">
        {/if}
    {/if}
    {if isset($with_cascade)}
                    <label for="cascade">{_T string="Cascade delete"}</label>
                    <input type="checkbox" name="cascade" id="cascade" value="true" title="{_T string="Delete all associated data"}"/>
    {/if}
    {if $mode neq 'ajax'}
        {if isset($with_cascade)}
                </div>
        {/if}
        {if isset($message)}
                <div class="ui warning message">
        {/if}
    {/if}
    {if isset($message)}
                    <p>{$message}</p>
    {/if}
    {if $mode neq 'ajax'}
        {if isset($message)}
                </div>
        {/if}
                <div class="ui red message">
    {/if}
                    <p>{_T string="Are you sure you want to proceed?"}<br/>{_T string="This can't be undone."}</p>
    {if $mode neq 'ajax'}
                </div>
            </div>
        </div>
        <div class="ui basic center aligned segment">
    {else}
        <div class="button-container">
    {/if}
            <input type="submit" id="delete" value="{_T string="Remove"}"{if $mode neq 'ajax'} class="ui primary button action"{/if}/>
            <a href="{$cancel_uri}" class="{if $mode neq 'ajax'}ui {/if}button" id="btncancel">{_T string="Cancel"}</a>
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
            {include file="forms_types/csrf.tpl"}
        </div>
    </form>
    </div>
{/block}

{block name="javascripts"}
{/block}
