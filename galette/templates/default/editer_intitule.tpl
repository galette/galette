{extends file="page.tpl"}

{block name="content"}
{assign var='name' value=$fields.libelle}
{assign var='id' value=$fields.id}
{assign var='field' value=$fields.third}

<form action="{path_for name="editEntitled" data=["class" => $url_class, "action" => "edit", "id" => $entry->$id]}" method="post" class="ui form">
    <div class="ui segment">
        <div class="field inline">
            <label for="{$name}">{_T string="Name:"}</label>
            <input type="text" name="{$name}" id="{$name}" value="{$entry->$name}" />
        </div>
        <div class="field inline">
            <label for="{$field}">
{if $class == 'Status'}
                {_T string="Priority:"}
{elseif $class == 'ContributionsTypes'}
                {_T string="Extends membership?"}
{/if}
            </label>
{if $class == 'Status'}
            <input type="text" size="4" name="{$field}" id="{$field}" value="{$entry->$field}" />
            <span class="exemple">{_T string="Note: members with a status priority lower than %priority are staff members." pattern="/%priority/" replace=$non_staff_priority}</span>
{elseif $class == 'ContributionsTypes'}
            <input type="checkbox" name="{$field}" id="{$field}" value="1"{if $entry->$field == 1} checked="checked"{/if} />
{/if}
        </div>
    </div>

    <input type="hidden" name="mod" id="mod" value="{$entry->$id}"/>
    <input type="hidden" name="class" value="{$class}" />

    <div class="button-container">
        <button type="submit" class="ui labeled icon button action">
            <i class="save icon"></i>
            {_T string="Save"}
        </button>
        {include file="forms_types/csrf.tpl"}
    </div>
</form>
{/block}
