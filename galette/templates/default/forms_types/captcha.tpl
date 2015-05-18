{extends file="forms_types/input.tpl"}

{block name="component"}
    {assign var="type" value="password"}
    {assign var="value" value=null}
    {assign var="example" value={_T string="Please repeat in the field the password shown in the image."}}
    {$smarty.block.parent}
{/block}

{block name="label"}
    {$smarty.block.parent}
    <input type="hidden" name="mdp_crypt" value="{$spam_pass}" />
    <img src="{$spam_img}" alt="{_T string="Password image"}" class="mdp_img" />
{/block}
