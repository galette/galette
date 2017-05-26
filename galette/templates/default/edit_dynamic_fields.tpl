{if !empty($dynamic_fields)}
<fieldset class="cssform">
    <legend class="ui-state-active ui-corner-top">{_T string="Additionnal fields:"}</legend>
    <div>
{foreach from=$dynamic_fields item=field}
{if $field.field_perm eq 0 || $login->isAdmin() || $login->isStaff() && $field.field_perm eq 2}
    {if $field.field_type eq 0}
        <div class="separator">{$field.field_name|escape}</div>
    {else}
        <p{if $field.config_field_repeat == 0 || $field.config_field_repeat > 1} class="repetable"{/if}>
            <label class="bline libelle" for="info_field_{$field.field_id}_1_{$field.field_repeat}">{$field.field_name|escape}</label>
    {* Number of configured occurences *}
    {assign var="count" value=$field.config_field_repeat}
    {if isset($data) and isset($data.dyn[$field.field_id]) and $data.dyn[$field.field_id]|@count > $field.config_field_repeat}
        {assign var="loops" value=$data.dyn[$field.field_id]|@count + 2}
    {elseif $field.config_field_repeat == 0 || $field.config_field_repeat > 1}
        {if isset($data) and isset($data.dyn[$field.field_id]) and $data.dyn[$field.field_id]|@count >= 2}
            {assign var="loops" value=$count + 1}
        {else}
            {assign var="loops" value="3"}
        {/if}
    {else}
        {assign var="loops" value="2"}
    {/if}

    {section name="fieldLoop" start=1 loop=$loops}
        {* Calculate field value *}
        {assign var="valuedata" value=null}
        {if isset($data.dyn[$field.field_id])}
            {if isset($data.dyn[$field.field_id][$smarty.section.fieldLoop.index])}
                {assign var="valuedata" value=$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]|escape}
            {/if}
        {/if}

        <!-- Create line break for each entry exept the first one -->
        {if $smarty.section.fieldLoop.index gt 1}<br/>{/if}
        {if $field.field_type eq 1}
            <textarea name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}" id="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}_{$count}"
                cols="{if $field.field_width > 0}{$field.field_width}{else}61{/if}"
                rows="{if $field.field_height > 0}{$field.field_height}{else}6{/if}"
                {if isset($disabled.dyn[$field.field_id])} {$disabled.dyn[$field.field_id]}{/if}
                {if $field.field_required eq 1} required{/if}>{$valuedata}</textarea>
        {elseif $field.field_type eq 2}
            <input type="text" name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}" id="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}_{$count}"
                {if $field.field_width > 0}size="{$field.field_width}"{/if}
                {if $field.field_size > 0}maxlength="{$field.field_size}"{/if}
                value="{$valuedata}"
                {if isset($disabled.dyn[$field.field_id])} {$disabled.dyn[$field.field_id]}{/if}
                {if $field.field_required eq 1} required{/if}
            />
        {elseif $field.field_type eq 3}
            <select name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}" id="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}_{$count}"{if $field.field_required eq 1} required{/if}>
                <!-- If no option is present, page is not XHTML compliant -->
                {if $field.choices|@count eq 0}<option value=""></option>{/if}
                {html_options options=$field.choices selected=$valuedata}
            </select>
        {elseif $field.field_type eq 4}
            <input type="text" name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}" id="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}_{$count}" maxlength="10"
                value="{$valuedata}" class="dynamic_date"
                {if isset($disabled.dyn[$field.field_id])} {$disabled.dyn[$field.field_id]}{/if}
                {if $field.field_required eq 1} required{/if}
            />
            <span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>
        {elseif $field.field_type eq 5}
            <input type="checkbox" name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}" id="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}_{$count}" value="1"
            {if $valuedata eq 1} checked="checked"{/if}
                {if isset($disabled.dyn[$field.field_id])} {$disabled.dyn[$field.field_id]}{/if}
                {if $field.field_required eq 1} required{/if}
            />
        {elseif $field.field_type eq 6}
            {if isset($data.dyn[$field.field_id])}
                {if isset($data.dyn[$field.field_id][$smarty.section.fieldLoop.index])}
                    {if !$data.dyn[$field.field_id][$smarty.section.fieldLoop.index]}
                        {assign var="disableddata" value=true}
                    {/if}
                {/if}
            {/if}
            {_T string="new"}: <input type="file" name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}" id="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}_{$count}_new"
                {if isset($disabled.dyn[$field.field_id])} {$disabled.dyn[$field.field_id]}{/if}
                {if $field.field_required eq 1} required{/if}
            />
            {_T string="current"}: <input type="text" name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}" id="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}_{$count}_current" disabled="disabled"
                value="{$valuedata}"
            />
            {_T string="delete"}: <input type="checkbox" name="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}" id="info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}_{$count}_delete"
                {if isset($disableddata)}disabled="disabled"{/if}
                onclick="this.form.info_field_{$field.field_id}_{$smarty.section.fieldLoop.index}_{$count}_new.disabled = this.checked;"
            />
        {/if}
    {/section}
        </p>
    {/if}
    {if $field.field_type neq 0}
        {if $field.config_field_repeat == 0 and $field.config_field_repeat neq null}
        <p class="exemple">{_T string="Enter as many occurences you want."}</p>
        {elseif $field.config_field_repeat > 1}
        <p class="exemple">{_T string="Enter up to %count occurences." pattern="/%count/" replace=$field.field_repeat}</p>
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
        var _total = _vals[_vals.length-1]; //max number of occurences
        var _current = _vals[_vals.length-2]; //current occurrence

       _a.click(function(e) {
            var _new = _input.clone();

            var _id = '';

            for ( var i = 0 ; i < _vals.length -2 ; i++ ) {
                _id += _vals[i] + '_';
            }

            _current = Number(_current) + 1;
            _new.attr('id', _id + _current + '_' + _total);
            _new.attr('name', _id + _current);
            _new.val('');
            _a.remove();
            _parent.append('<br/>');
            _parent.append(_new);
            _new.focus();
            if( _total === '0' || _current < _total ) {
                var _b = _addLnk();
                _lnkEvent(_b, _new, _parent);
                _parent.append(_b);
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
                var _total = _vals[_vals.length-1]; //max number of occurences
                var _current = _vals[_vals.length-2]; //current occurrence

                if ( _total === '0' || _current < _total ) {
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
            buttonImage: '{base_url}/{$template_subdir}images/calendar.png',
            buttonImageOnly: true,
            buttonText: '{_T string="Select a date" escape="js"}'
        });
    });
</script>
{/if}
