{if isset($mode) && $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}

{block name="content"}
    <div id="mass_contributions"{if $mode neq 'ajax'} class="center"{else} title="{$page_title}"{/if}>
    <form action="{$form_url}" method="post">
        {if $mode neq 'ajax'}<h2>{$page_title}</h2>{/if}
        <label for="type">{_T string="Contribution type"}</label>
        <select name="type" id="type">
            <option value="{constant('Galette\Entity\Contribution::TYPE_FEE')}">{_T string="Membership"}</option>
            <option value="{constant('Galette\Entity\Contribution::TYPE_DONATION')}">{_T string="Donation"}</option>
        </select>
        <div class="button-container">
            <input type="submit" id="masschange" class="button" value="{_T string="OK"}"/>
            <a href="{$cancel_uri}" class="button" id="btncancel">{_T string="Cancel"}</a>
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
