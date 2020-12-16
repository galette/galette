{if !empty($object->getDynamicFields())}
    {if !empty($object->getDynamicFields()->getFields())}

{function name=draw_field}
    {assign var=valuedata value=$field_data.field_val|escape}
    {if $field|is_a:'Galette\DynamicFields\File'}
    <span class="bline libelle">{$field->getName()|escape}</span>
    {else}
    <label class="bline libelle" for="info_field_{$field->getId()}_{$loop}">{$field->getName()|escape}</label>
    {/if}
    {if $field|is_a:'Galette\DynamicFields\Text'}
        <textarea name="info_field_{$field->getId()}_{$loop}" id="info_field_{$field->getId()}_{$loop}"
            cols="{if $field->getWidth() > 0}{$field->getWidth()}{else}61{/if}"
            rows="{if $field->getHeight() > 0}{$field->getHeight()}{else}6{/if}"
            {if $field->isRepeatable()} data-maxrepeat="{$field->getRepeat()}"{/if}
            {if $field->isRequired()} required="required"{/if}
            {if $disabled} disabled="disabled"{/if}>{$valuedata}</textarea>
    {elseif $field|is_a:'Galette\DynamicFields\Line'}
        <input type="text" name="info_field_{$field->getId()}_{$loop}" id="info_field_{$field->getId()}_{$loop}"
            {if $field->getWidth() > 0}size="{$field->getWidth()}"{/if}
            {if $field->getSize() > 0}maxlength="{$field->getSize()}"{/if}
            value="{$valuedata}"
            {if $field->isRequired()} required="required"{/if}
            {if $disabled} disabled="disabled"{/if}
            {if $field->isRepeatable()} data-maxrepeat="{$field->getRepeat()}"{/if}
        />
    {elseif $field|is_a:'Galette\DynamicFields\Choice'}
        <select name="info_field_{$field->getId()}_{$loop}" id="info_field_{$field->getId()}_{$loop}"
            {if $field->isRequired()} required="required"{/if}
            {if $disabled} disabled="disabled"{/if}
            {if $field->isRepeatable()} data-maxrepeat="{$field->getRepeat()}"{/if}
        >
            <!-- If no option is present, page is not XHTML compliant -->
            <option value="">{_T string="Select an option"}</option>
            {html_options options=$field->getValues() selected=$valuedata}
        </select>
    {elseif $field|is_a:'Galette\DynamicFields\Date'}
        <input type="text" name="info_field_{$field->getId()}_{$loop}" id="info_field_{$field->getId()}_{$loop}" maxlength="10"
            value="{$valuedata}" class="dynamic_date modif_date"
            {if $field->isRepeatable()} data-maxrepeat="{$field->getRepeat()}"{/if}
            {if $field->isRequired()} required="required"{/if}
            {if $disabled} disabled="disabled"{/if}
        />
        <span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>
    {elseif $field|is_a:'Galette\DynamicFields\Boolean'}
        <input type="checkbox" name="info_field_{$field->getId()}_{$loop}" id="info_field_{$field->getId()}_{$loop}" value="1"
            {if $valuedata eq 1} checked="checked"{/if}
            {if $field->isRepeatable()} data-maxrepeat="{$field->getRepeat()}"{/if}
            {if $field->isRequired()} required="required"{/if}
            {if $disabled} disabled="disabled"{/if}
        />
    {elseif $field|is_a:'Galette\DynamicFields\File'}
        <label class="labelalign" for="info_field_{$field->getId()}_{$loop}_new">{_T string="new"}</label> <input type="file" name="info_field_{$field->getId()}_{$loop}" id="info_field_{$field->getId()}_{$loop}_new"
            {if $field->isRequired() and $valuedata eq ''} required="required"{/if}
            {if $disabled} disabled="disabled"{/if}
        />
        <label class="labelalign" for="info_field_{$field->getId()}_{$loop}_current">{_T string="current"}</label> <input type="text" name="info_field_{$field->getId()}_{$loop}" id="info_field_{$field->getId()}_{$loop}_current" disabled="disabled"
            value="{$valuedata}"
        />
        <label class="labelalign" for="info_field_{$field->getId()}_{$loop}_delete">{_T string="delete"}</label> <input type="checkbox" name="info_field_{$field->getId()}_{$loop}" id="info_field_{$field->getId()}_{$loop}_delete"
            onclick="this.form.info_field_{$field->getId()}_{$loop}_new.disabled = this.checked;"
        />
    {/if}
{/function}

<fieldset class="galette_form">
    <legend class="ui-state-active ui-corner-top">{_T string="Additionnal fields:"}</legend>
    <div>
    {assign var=access_level value=$login->getAccessLevel()}
    {foreach from=$object->getDynamicFields()->getFields() item=field}
        {assign var=perm value=$field->getPerm()}
        {if $field|is_a:'Galette\DynamicFields\Separator'}
        <div class="separator">{$field->getName()|escape}</div>
        {else}
        <p{if $field->isRepeatable()} class="repetable"{/if}>
            {assign var=disabled value=false}
            {if $perm eq constant('Galette\DynamicFields\DynamicField::PERM_USER_READ') && $access_level eq constant('Galette\Core\Authentication::ACCESS_USER')}
                {assign var=disabled value=true}
            {/if}
            {assign var=values value=$object->getDynamicFields()->getValues($field->getId())}
            {assign var=can_add value=false}
            {if $field->getRepeat() === 0 || !is_array($values) || $values|@count < $field->getRepeat() || $values|@count === 0}
                {assign var=can_add value=true}
            {/if}
            {foreach from=$values item=field_data}
                {if not $field_data@first}<br/>{/if}
                {draw_field field=$field field_data=$field_data disabled=$disabled loop=$field_data@iteration}
            {/foreach}
            {if !is_array($values) || $values|@count === 0}
                {$field_data = ['field_val' => '']}
                {if (is_array($values))}
                    {assign var="current_count" value=$values|@count}
                {else}
                    {assign var="current_count" value=0}
                {/if}
                {draw_field field=$field field_data=$field_data disabled=$disabled loop=$current_count + 1}
            {/if}
        </p>
            {if $field->isRepeatable()}
                {if $field->getRepeat() === 0}
        <p class="exemple" id="repeat_msg">{_T string="Enter as many occurences you want."}</p>
                {elseif !is_array($values) || $values|@count < $field->getRepeat() || $values|@count === 0}
                    {if (is_array($values))}
                        {assign var="current_count" value=$values|@count}
                    {else}
                        {assign var="current_count" value=1}
                    {/if}
                    {assign var=remaining value=$field->getRepeat() - $current_count}
        <p class="exemple" id="repeat_msg">{_T string="Enter up to %count more occurences." pattern="/%count/" replace=$remaining}</p>
                {/if}
            {/if}
        {/if}
    {/foreach}
    </div>
</fieldset>
<script type="text/javascript">
    var _addLnk = function(){
        return $('<a href="#"><img src="{base_url}/{$template_subdir}images/icon-add.png" alt="{_T string="New occurence"}"/></a>');
    };

    var _lnkEvent = function(_a, _input, _parent) {
        var _vals = _input[0].id.split(/_/);
        var _total = $(_input[0]).data('maxrepeat'); //max number of occurences
        var _current = _vals[_vals.length-1]; //current occurrence

       _a.click(function(e) {
            var _new = _input.clone();

            var _id = '';

            for ( var i = 0 ; i < _vals.length -1 ; i++ ) {
                _id += _vals[i] + '_';
            }

            _current = Number(_current) + 1;
            _new.attr('id', _id + _current);
            _new.attr('name', _id + _current);
            _new.val('');
            _a.remove();
            _parent.append('<br/>');
            _parent.append(_new);
            _new.focus();
            if( _total == '0' || _current < _total ) {
                var _b = _addLnk();
                _lnkEvent(_b, _new, _parent);
                _parent.append(_b);
                if (_current < _total) {
                    $('#repeat_msg').html('{_T string="Enter up to %count more occurences." pattern="/%count/" replace="COUNT" escape="js"}'.replace(/COUNT/, _total - _current));
                }
            } else if (_current == _total) {
                $('#repeat_msg').remove();
            }
            return false;
        });
    }

    $(function(){
        $('.repetable').each(function(){
            var _total;
            var _current;
            var _parent = $(this);

            var _input = $(this).find('input:last');
            if ( _input.length > 0 ) {
                while ( $(this).find('input').length > 1 && _input.val() == '' ) {
                    _input.prev('br').remove();
                    _input.remove();
                    _input = $(this).find('input:last')
                }
                var _vals = _input[0].id.split(/_/);
                var _total = $(_input[0]).data('maxrepeat'); //max number of occurences
                var _current = _vals[_vals.length-1]; //current occurrence

                if ( _total == '0' || _current < _total ) {
                    var _a = _addLnk();
                    $(this).append(_a);
                    _lnkEvent(_a, _input, _parent);
                }
            }
        });
        $('.dynamic_date').datepicker({
            changeMonth: true,
            changeYear: true,
            showOn: 'button',
            buttonText: '<i class="far fa-calendar-alt"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
        });
    });
</script>
    {/if}
{/if}
