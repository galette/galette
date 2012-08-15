{if !empty($dynamic_fields)}
<table class="details">
    <caption class="ui-state-active ui-corner-top">{_T string="Additionnal fields:"}</caption>
    {foreach from=$dynamic_fields item=field}
        {if $field.field_perm ne 1 || $login->isAdmin() || $login->isStaff()}
            {if $field.field_type eq 0}
    <tr>
        <th class="separator" colspan="2">{$field.field_name|escape}</th>
    </tr>
            {else}
    <tr>
        <th>{$field.field_name|escape}</th>
        <td>
                {section name="fieldLoop" start=1 loop=$field.field_repeat+1}
            {$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]|nl2br|default:"&nbsp;"}<br/>
                {/section}
        </td>
    </tr>
            {/if}
        {/if}
    {/foreach}
</table>
{/if}
