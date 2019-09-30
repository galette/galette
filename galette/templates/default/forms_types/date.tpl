{extends file="forms_types/input.tpl"}

{block name="component"}
    {assign var="type" value="text"}
    {assign var="example" value={_T string="(yyyy-mm-dd format)"}}
    {if $id eq 'ddn_adh'}
        {assign var="example" value={_T string="(yyyy-mm-dd format)"}|cat:"<span id=\"member_age\">{$member->getAge()}</span>"}
    {/if}
    {assign var="component_class" value="field"}
    {$smarty.block.parent}
{/block}

{block name="element"}
    <div class="ui calendar" id="birth-rangestart">
        <div class="ui input left icon">
            <i class="calendar icon"></i>
            {$smarty.block.parent}
        </div>
    </div>
{/block}
