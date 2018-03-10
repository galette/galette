{if $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}
{block name="content"}
    <div id="mass_change"{if $mode neq 'ajax'} class="center"{else} title="{$page_title}"{/if}>
    <form action="{$form_url}" method="post">
        {if $mode neq 'ajax'}<h2>{$page_title}</h2>{/if}
    {if !isset($changes)}
        <p>{_T string="Only checked fields will be updated."}</p>
    {else}
        <p>{_T string="You are about to proceed following changes for selected members:"}</p>
        <ul>
        {foreach $changes as $field => $change}
            {assign var="display_value" value=$change.value}
            {if $field eq 'id_statut'}
                {assign var="display_value" value=$statuts[$display_value]}
            {/if}
            {if $field eq 'titre_adh'}
                {assign var="display_value" value=$titles_list[$display_value]->long}
            {/if}
            {if $field eq 'sexe_adh'}
                {if $display_value eq {Galette\Entity\Adherent::NC}}
                    {assign var="display_value" value={_T string="Unspecified"}}
                {/if}
                {if $display_value eq {Galette\Entity\Adherent::WOMAN}}
                    {assign var="display_value" value={_T string="Woman"}}
                {/if}
                {if $display_value eq {Galette\Entity\Adherent::MAN}}
                    {assign var="display_value" value={_T string="Man"}}
                {/if}
            {/if}
            <li>
                <input type="hidden" name="{$field}" value="{$change.value}"/>
                {$change.label} {$display_value}
            </li>
        {/foreach}
        </ul>
    {/if}
        <div class="button-container">
    {if !isset($changes)}
        {* Form entries*}
        {include file="forms_types.tpl" masschange=true}
    {/if}
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
