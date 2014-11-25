{if !empty($dynamic_fields)}
<table class="details">
    <caption class="ui-state-active ui-corner-top">{_T string="Additionnal fields:"}</caption>
    {foreach from=$dynamic_fields item=field}
        {if $field.field_perm eq 0 || $login->isAdmin() || $login->isStaff() && $field.field_perm eq 2}
            {if $field.field_type eq 0}
    <tr>
        <th class="separator" colspan="2">{$field.field_name|escape}</th>
    </tr>
            {else}
    <tr>
        <th>{$field.field_name|escape}</th>
        <td>
                {section name="fieldLoop" start=1 loop=$field.field_repeat+1}
            {if isset($data.dyn[$field.field_id][$smarty.section.fieldLoop.index]) and GaletteMail::isValidEmail($data.dyn[$field.field_id][$smarty.section.fieldLoop.index])}
                {if $smarty.section.fieldLoop.index_prev > 0}<br />{/if}
                <a href="mailto:{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}">{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}</a>
            {else if isset($data.dyn[$field.field_id][$smarty.section.fieldLoop.index]) and GaletteMail::isUrl($data.dyn[$field.field_id][$smarty.section.fieldLoop.index])}
                {if $smarty.section.fieldLoop.index_prev > 0}<br />{/if}
                <a href="{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}" target="_blank" title="{_T string="Open '%s' in a new window" replace=$data.dyn[$field.field_id][$smarty.section.fieldLoop.index] pattern="/%s/"}">{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}</a>
            {else if isset($data.dyn[$field.field_id][$smarty.section.fieldLoop.index]) and $field.field_type eq 5}
            {if $data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}
                {_T string="Yes"}
            {else}
                {_T string="No"}
            {/if}
            <br/>
            {else if isset($data.dyn[$field.field_id][$smarty.section.fieldLoop.index]) and $field.field_type eq 6}
            <a href="get_file.php?file=member_{$member->id}_field_{$field.field_id}_value_{$smarty.section.fieldLoop.index}&name={$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}">{$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}</a><br/>
            {else}
            {$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]|nl2br|default:"&nbsp;"}<br/>
            {/if}
                {/section}
        </td>
    </tr>
            {/if}
        {/if}
    {/foreach}
</table>
{/if}
