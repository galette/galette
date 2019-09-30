{extends file="forms_types/input.tpl"}

{block name="component"}
    {assign var="required" value=true}
    {assign var="type" value="gaptcha"}
    {assign var="name" value="gaptcha"}
    {assign var="id" value="gaptcha"}
    {assign var="value" value=null}
    {assign var="example" value={_T string="(numbers only)"}}
    {assign var="tip" value={_T string="This field is required trying to avoid registration spam. We are sorry for the inconvennience."}}
    {assign var="component_class" value="field required"}
    {$smarty.block.parent}
{/block}

{block name="label"}
    {assign var="label" value="Captcha"}
    {$smarty.block.parent}
    {$gaptcha->generateQuestion()}
{/block}
