{extends file='ajouter_contribution.tpl'}
{block name="content"}
    <div id="mass_contributions"{if $mode neq 'ajax'} class="center"{else} title="{$page_title}"{/if}>
    <form action="{$form_url}" method="post">
        {if $mode neq 'ajax'}<h2>{$page_title}</h2>{/if}
        <div class="button-container">
            {$smarty.block.parent}
            <input type="submit" id="masschange" class="button" value="{if !isset($changes)}{_T string="Edit"}{else}{_T string="OK"}{/if}"/>
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
