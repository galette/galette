{if $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}
{block name="content"}
    <div id="confirm_removal"{if $mode neq 'ajax'} class="center"{/if}>
    <form action="{$form_url}" method="post">
        <h2>{$page_title}</h2>
        <p>{_T string="Are you sure you want to proceed?"}<br/>{_T string="This can't be undone."}
        <div class="button-container">
            <input type="submit" id="delete" value="{_T string="Remove"}"/>
            <a href="{$cancel_uri}" class="button" id="btncancel">{_T string="Cancel"}</a>
            <input type="hidden" name="confirm" value="1"/>
            {foreach $data as $key=>$value}
                <input type="hidden" name="{$key}" value="{$value}"/>
            {/foreach}
        </div>
    </form>
    </div>
{/block}

{block name="javascripts"}
{/block}
