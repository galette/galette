{assign var='name' value=$fields.$class.name}
{assign var='id' value=$fields.$class.id}
<form action="gestion_intitules.php" method="post" enctype="multipart/form-data">
    <div class="bigtable">
    <fieldset class="cssform" id="general">
        <p>
            <label for="{$fields.$class.name}" class="bline">{_T string="Name:"}</label>
            <input type="text" name="{$fields.$class.name}" id="{$fields.$class.name}" value="{$entry->$name}" />
        </p>
        <p>
            <label for="{$fields.$class.field}" class="bline">
{if $class == 'Status'}
                {_T string="Priority:"}
{elseif $class == 'ContributionsTypes'}
                {_T string="Extends membership?"}
{/if}
            </label>
{assign var='field' value=$fields.$class.field}
{if $class == 'Status'}
            <input type="text" size="4" name="{$fields.$class.field}" id="{$fields.$class.field}" value="{$entry->$field}" />
            <span class="exemple">{_T string="Note: members with a status priority lower than %priority are staff members." pattern="/%priority/" replace=$non_staff_priority}</span>
{elseif $class == 'ContributionsTypes'}
            <input type="checkbox" name="{$fields.$class.field}" id="{$fields.$class.field}" value="1"{if $entry->$field == 1} checked="checked"{/if} />
{/if}
        </p>
    </fieldset>

    <input type="hidden" name="mod" id="mod" value="{$entry->$id}"/>
    <input type="hidden" name="class" value="{$class}" />

    <div class="button-container">
        <input type="submit" value="{_T string="Save"}" />
    </div>
    </div>
</form> 
