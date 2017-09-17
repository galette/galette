{extends file="page.tpl"}

{block name="content"}
{assign var='name' value=$fields.libelle}
{assign var='id' value=$fields.id}
{assign var='field' value=$fields.third}

<form action="{path_for name="editEntitled" data=["class" => $url_class, "action" => {_T string="edit" domain="routes"}, "id" => $entry->$id]}" method="post">
    <div class="bigtable">
    <fieldset class="cssform" id="general">
        <div>
        <p>
            <label for="{$name}" class="bline">{_T string="Name:"}</label>
            <input type="text" name="{$name}" id="{$name}" value="{$entry->$name}" />
        </p>
        <p>
            <label for="{$field}" class="bline">
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
        </p>
        </div>
    </fieldset>

    <input type="hidden" name="mod" id="mod" value="{$entry->$id}"/>
    <input type="hidden" name="class" value="{$class}" />

    <div class="button-container">
        <input type="submit" value="{_T string="Save"}" />
    </div>
    </div>
</form>
{/block}
