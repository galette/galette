{if !empty($object->getDynamicFields())}
<div class="ui basic fitted segment">
    <div class="ui styled fluid accordion row">
        <div class="active title">
            <i class="icon dropdown"></i>
            {_T string="Additionnal fields:"}
        </div>
        <div class="active content field">
            <table class="ui very basic striped stackable padded table">
                {foreach from=$object->getDynamicFields()->getFields() item=field}
                    {if $field|is_a:'Galette\DynamicFields\Separator'}
                <tr>
                    <td colspan="2"><div class="ui horizontal divider">{$field->getName()|escape}</div></td>
                </tr>
                    {else}
                <tr>
                    <th class="three wide column">{$field->getName()|escape}</th>
                    <td>
                        {foreach from=$object->getDynamicFields()->getValues($field->getId()) item=field_data}
                            {assign var=value value=$field_data.field_val}
                            {if $field|is_a:'Galette\DynamicFields\Choice'}
                                {if isset($field_data.text_val)}
                                    {assign var=value value=$field_data.text_val}
                                {else}
                                    {assign var=value value=""}
                                {/if}
                            {/if}
                            {if not $field_data@first}<br />{/if}
                            {if $field|is_a:'Galette\DynamicFields\Boolean'}
                                {if $value}
                        {_T string="Yes"}
                                {else}
                        {_T string="No"}
                                {/if}
                            {else if $field|is_a:'Galette\DynamicFields\File'}
                        <a href="{path_for name="getDynamicFile" data=["id" => $object->id, "fid" => $field->getId(), "pos" => $field_data@iteration, "name" => $value]}">{$value}</a>
                            {else if $field|is_a:'Galette\DynamicFields\Line' and GaletteMail::isValidEmail($value)}
                                <a href="mailto:{$value}">{$value}</a>
                            {else if $field|is_a:'Galette\DynamicFields\Line' and GaletteMail::isUrl($value)}
                                <a href="{$value}" target="_blank" title="{_T string="Open '%s' in a new window" replace=$value pattern="/%s/"}">{$value}</a>
                            {else}
                        {$value|nl2br|default:"&nbsp;"}
                            {/if}
                        {/foreach}
                    </td>
                </tr>
                    {/if}
                {/foreach}
            </table>
        </div>
    </div>
</div>
{/if}
