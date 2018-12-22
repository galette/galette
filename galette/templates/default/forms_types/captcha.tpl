{extends file="forms_types/input.tpl"}

{block name="component"}
    {assign var="type" value="password"}
    {assign var="value" value=null}
    {assign var="example" value={_T string="Please repeat in the field the password shown in the image."}}
    {assign var="tip" value={_T string="A link will be sent to you if you have provided an email address. Otherwise, you can use this password to login, and we recommend you to change it as soon as possible."}}
    {$smarty.block.parent}
{/block}

{block name="label"}
    {$smarty.block.parent}
    <input type="hidden" name="mdp_crypt" value="{$spam_pass}" />
    <img src="{$spam_img}" alt="{_T string="Password image"}" class="mdp_img" />
{/block}
