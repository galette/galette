{extends file="page.tpl"}

{block name="content"}
        <form action="{path_for name="filter-memberslist"}" method="post" id="filtre">
            <fieldset class="cssform large">
                <legend class="ui-state-active ui-corner-top">{_T string="Simple search"}</legend>
                <div>
                    <p>
                        <label class="bline" for="filter_str">{_T string="Search:"}</label>
                        <input type="text" name="filter_str" id="filter_str" value="{$filters->filter_str}" type="search" placeholder="{_T string="Enter a value"}"/>&nbsp;
                        {_T string="in:"}&nbsp;
                        <select name="filter_field">
                            {html_options options=$filter_field_options selected=$filters->field_filter}
                        </select>
                    </p>
                    <p>
                        <label class="bline" for="filter_membership">{_T string="Membership status"}</label>
                        <select id="filter_membership" name="filter_membership">
                            {html_options options=$filter_membership_options selected=$filters->membership_filter}
                        </select>
                    </p>
                    <p>
                        <label class="bline" for="filter_account">{_T string="Account activity"}</label>
                        <select id="filter_account" name="filter_account">
                            {html_options options=$filter_accounts_options selected=$filters->account_status_filter}
                        </select>
                    </p>
                    <p>
                        <label class="bline" for="group_filter">{_T string="Member of group"}</label>
                        <select name="group_filter">
                            <option value="0">{_T string="Select a group"}</option>
{foreach from=$filter_groups_options item=group}
                            <option value="{$group->getId()}"{if $filters->group_filter eq $group->getId()} selected="selected"{/if}>{$group->getName()}</option>
{/foreach}
                        </select>
                    <p>
                        <span class="bline">{_T string="With mail:"}</span>
                        <input type="radio" name="email_filter" id="filter_dc_email" value="{Galette\Repository\Members::FILTER_DC_EMAIL}"{if $filters->email_filter eq constant('Galette\Repository\Members::FILTER_DC_EMAIL')} checked="checked"{/if}>
                        <label for="filter_dc_email" >{_T string="Don't care"}</label>
                        <input type="radio" name="email_filter" id="filter_with_email" value="{Galette\Repository\Members::FILTER_W_EMAIL}"{if $filters->email_filter eq constant('Galette\Repository\Members::FILTER_W_EMAIL')} checked="checked"{/if}>
                        <label for="filter_with_email" >{_T string="With"}</label>
                        <input type="radio" name="email_filter" id="filter_without_email" value="{Galette\Repository\Members::FILTER_WO_EMAIL}"{if $filters->email_filter eq constant('Galette\Repository\Members::FILTER_WO_EMAIL')} checked="checked"{/if}>
                        <label for="filter_without_email" >{_T string="Without"}</label>
                    </p>
                </div>
            </fieldset>
            <fieldset class="cssform large">
                <legend class="ui-state-active ui-corner-top">{_T string="Advanced search"}</legend>
                <div>
                     <p>
                        <span class="bline">{_T string="Birth date"}</span>
                        <label for="birth_date_begin">{_T string="beetween"}</label>
                        <input id="birth_date_begin" name="birth_date_begin" type="text" class="modif_date" maxlength="10" size="10" value="{$filters->birth_date_begin}"/>
                        <label for="birth_date_end">{_T string="and"}</label>
                        <input id="birth_date_end" name="birth_date_end" type="text" class="modif_date" maxlength="10" size="10" value="{$filters->birth_date_end}"/>
                    </p>
                    <p>
                        <span class="bline">{_T string="Creation date"}</span>
                        <label for="creation_date_begin">{_T string="beetween"}</label>
                        <input id="creation_date_begin" name="creation_date_begin" type="text" class="modif_date" maxlength="10" size="10" value="{$filters->creation_date_begin}"/>
                        <label for="creation_date_end">{_T string="and"}</label>
                        <input id="creation_date_end" name="creation_date_end" type="text" class="modif_date" maxlength="10" size="10" value="{$filters->creation_date_end}"/>
                    </p>
                    <p>
                        <span class="bline">{_T string="Modification date"}</span>
                        <label for="modif_date_begin">{_T string="beetween"}</label>
                        <input id="modif_date_begin" name="modif_date_begin" type="text" class="modif_date" maxlength="10" size="10" value="{$filters->modif_date_begin}"/>
                        <label for="modif_date_end">{_T string="and"}</label>
                        <input id="modif_date_end" name="modif_date_end" type="text" class="modif_date" maxlength="10" size="10" value="{$filters->modif_date_end}"/>
                    </p>
                    <p>
                        <span class="bline">{_T string="Due date"}</span>
                        <label for="due_date_begin">{_T string="beetween"}</label>
                        <input id="due_date_begin" name="due_date_begin" type="text" class="due_date" maxlength="10" size="10" value="{$filters->due_date_begin}"/>
                        <label for="due_date_end">{_T string="and"}</label>
                        <input id="due_date_end" name="due_date_end" type="text" class="due_date" maxlength="10" size="10" value="{$filters->due_date_end}"/>
                    </p>
                    <p>
                        <span class="bline">{_T string="Show public infos"}</span>
                        <input type="radio" name="show_public_infos" id="show_public_infos_dc" value="{Galette\Repository\Members::FILTER_DC_PUBINFOS}"{if $filters->show_public_infos eq constant('Galette\Repository\Members::FILTER_DC_PUBINFOS')} checked="checked"{/if}>
                        <label for="show_public_infos_dc" >{_T string="Don't care"}</label>
                        <input type="radio" name="show_public_infos" id="show_public_infos_yes" value="{Galette\Repository\Members::FILTER_W_PUBINFOS}"{if $filters->show_public_infos eq constant('Galette\Repository\Members::FILTER_W_PUBINFOS')} checked="checked"{/if}>
                        <label for="show_public_infos_yes" >{_T string="Yes"}</label>
                        <input type="radio" name="show_public_infos" id="show_public_infos_no" value="{Galette\Repository\Members::FILTER_WO_PUBINFOS}"{if $filters->show_public_infos eq constant('Galette\Repository\Members::FILTER_WO_PUBINFOS')} checked="checked"{/if}>
                        <label for="show_public_infos_no" >{_T string="No"}</label>
                    </p>
                    <p>
                        <label class="bline" for="status">{_T string="Statuts"}</label>
                        <select name="status[]" id="status" multiple="multiple">
                            {html_options options=$statuts selected=$filters->status}
                        </select>
                    </p>
                </div>
            </fieldset>
            <fieldset class="cssform large">
                <legend class="ui-state-active ui-corner-top">{_T string="Within contributions"}</legend>
                <div>
                    <p>
                        <span class="bline">{_T string="Creation date"}</span>
                        <label for="contrib_creation_date_begin">{_T string="beetween"}</label>
                        <input id="contrib_creation_date_begin" name="contrib_creation_date_begin" type="text" class="modif_date" maxlength="10" size="10" value="{$filters->contrib_creation_date_begin}"/>
                        <label for="contrib_creation_date_end">{_T string="and"}</label>
                        <input id="contrib_creation_date_end" name="contrib_creation_date_end" type="text" class="modif_date" maxlength="10" size="10" value="{$filters->contrib_creation_date_end}"/>
                    </p>
                    <p>
                        <span class="bline">{_T string="Begin date"}</span>
                        <label for="contrib_begin_date_begin">{_T string="beetween"}</label>
                        <input id="contrib_begin_date_begin" name="contrib_begin_date_begin" type="text" class="modif_date" maxlength="10" size="10" value="{$filters->contrib_begin_date_begin}"/>
                        <label for="contrib_begin_date_end">{_T string="and"}</label>
                        <input id="contrib_begin_date_end" name="contrib_begin_date_end" type="text" class="modif_date" maxlength="10" size="10" value="{$filters->contrib_begin_date_end}"/>
                    </p>
                    <p>
                        <span class="bline">{_T string="End date"}</span>
                        <label for="contrib_end_date_begin">{_T string="beetween"}</label>
                        <input id="contrib_end_date_begin" name="contrib_end_date_begin" type="text" class="due_date" maxlength="10" size="10" value="{$filters->contrib_end_date_begin}"/>
                        <label for="contrib_end_date_end">{_T string="and"}</label>
                        <input id="contrib_end_date_end" name="contrib_end_date_end" type="text" class="due_date" maxlength="10" size="10" value="{$filters->contrib_end_date_end}"/>
                    </p>
                    <p>
                        <span class="bline">{_T string="Amount"}</span>
                        <label for="contrib_min_amount">{_T string="beetween"}</label>
                        <input id="contrib_min_amount" name="contrib_min_amount" type="text" maxlength="10" size="10" value="{$filters->contrib_min_amount}"/>
                        <label for="contrib_max_amount">{_T string="and"}</label>
                        <input id="contrib_max_amount" name="contrib_max_amount" type="text" maxlength="10" size="10" value="{$filters->contrib_max_amount}"/>
                    </p>
                    <p>
                        <label class="bline" for="contributions_types">{_T string="Type"}</label>
                        <select name="contributions_types[]" id="contributions_types" multiple="multiple">
                            {html_options options=$contributions_types selected=$filters->contributions_types}
                        </select>
                    </p>
                    <p>
                        <label class="bline" for="payments_types">{_T string="Payment type"}</label>
                        <select name="payments_types[]" id="payments_types" multiple="multiple">
                            {html_options options=$payments_types selected=$filters->payments_types}
                        </select>
                    </p>
{foreach $cdynamic_fields as $field}
    {assign var=fid value=$field.field_id}
    {if $field.field_type eq constant('Galette\Entity\DynamicFields::CHOICE')}
        {assign var=rid value="cdsc_$fid"}
    {else}
        {assign var=rid value="cds_$fid"}
    {/if}
                    <p>
                        <label class="bline" for="cds{if $field.field_type eq constant('Galette\Entity\DynamicFields::CHOICE')}c{/if}_{$field.field_id}">{$field.field_name}</label>
    {if $field.field_type eq constant('Galette\Entity\DynamicFields::LINE')}
                        <input type="text" name="cds{if $field.field_type eq constant('Galette\Entity\DynamicFields::CHOICE')}c{/if}_{$field.field_id}" id="cds{if $field.field_type eq constant('Galette\Entity\DynamicFields::CHOICE')}c{/if}_{$field.field_id}" value="{if isset($filters->contrib_dynamic.$rid)}{$filters->contrib_dynamic.$rid}{/if}" />
    {elseif $field.field_type eq constant('Galette\Entity\DynamicFields::TEXT')}
                        <textarea name="cds{if $field.field_type eq constant('Galette\Entity\DynamicFields::CHOICE')}c{/if}_{$field.field_id}" id="cds{if $field.field_type eq constant('Galette\Entity\DynamicFields::CHOICE')}c{/if}_{$field.field_id}">{if isset($filters->contrib_dynamic.$rid)}{$filters->contrib_dynamic.$rid}{/if}</textarea>
    {elseif $field.field_type eq constant('Galette\Entity\DynamicFields::CHOICE')}
                        <select name="cds{if $field.field_type eq constant('Galette\Entity\DynamicFields::CHOICE')}c{/if}_{$field.field_id}[]" id="cds{if $field.field_type eq constant('Galette\Entity\DynamicFields::CHOICE')}c{/if}_{$field.field_id}" multiple="multiple">
                            <option value="">{_T string="Select"}</option>
        {foreach $field.choices item=choice key=k}
                            <option value="{$k}"{if isset($cds.field) and  $cds.field eq $rid} selected="selected"{/if}>{$choice}</option>
        {/foreach}
                        </select>
    {/if}
                    </p>
{/foreach}
                </div>
            </fieldset>
            <fieldset class="cssform large">
                <legend class="ui-state-active ui-corner-top">{_T string="Free search"}<a class="clearfilter" href="#" title="{_T string="Add new free search criteria"}" id="btnadd_small">{_T string="Add"}</a></legend>
                <ul id="fs_sortable" class="fields_list connectedSortable">
{foreach from=$filters->free_search item=fs}
                    <li>
                        <select name="free_logical_operator[]" class="operator_selector">
                            <option value="{Galette\Filters\AdvancedMembersList::OP_AND}"{if $fs.log_op eq constant('Galette\Filters\AdvancedMembersList::OP_AND')} selected="selected"{/if}>{_T string="and"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_OR}"{if $fs.log_op eq constant('Galette\Filters\AdvancedMembersList::OP_OR')} selected="selected"{/if}>{_T string="or"}</option>
                        </select>
                        <select name="free_field[]" class="field_selector">
                            <option value="">{_T string="Select a field"}</option>
    {foreach $search_fields as $field}
        {if $fs.field eq $field@key}
            {if $field@key|strpos:'date_' === 0 or $field@key eq 'ddn_adh'}
                {assign var=type value=constant('Galette\Entity\DynamicFields::DATE')}
            {else}
                {assign var=type value=constant('Galette\Entity\DynamicFields::LINE')}
            {/if}
        {/if}
                            <option value="{$field@key}"{if $fs.field eq $field@key} selected="selected"{/if}>{$field.label}</option>
    {/foreach}
    {foreach $dynamic_fields as $field}
        {if $field.field_type eq constant('Galette\Entity\DynamicFields::SEPARATOR')}
                {continue}
        {/if}
        {assign var=fid value=$field.field_id}
        {assign var=rid value="dyn_$fid"}
                            <option value="dyn_{$field.field_id}"{if $fs.field eq $rid} selected="selected"{assign var=type value=$field.field_type}{assign var=choices value=$field.choices}{/if}>{$field.field_name}</option>
    {/foreach}
                        </select>
                        <span>
                            <input type="hidden" name="free_type[]" value="{$type}"/>
    {if $type eq constant('Galette\Entity\DynamicFields::CHOICE')}
                        <select name="free_query_operator[]">
                            <option value="{Galette\Filters\AdvancedMembersList::OP_EQUALS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_EQUALS')} selected="selected"{/if}>{_T string="is"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_NOT_EQUALS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_NOT_EQUALS')} selected="selected"{/if}>{_T string="is not"}</option>
                        </select>
                        <select name="free_text[]">
        {foreach $choices as $choice}
                            <option value="{$choice@index}"{if $fs.search eq $choice@index} selected="selected"{/if}>{$choice}</option>
        {/foreach}
                        </select>
    {elseif $type eq constant('Galette\Entity\DynamicFields::DATE')}
                        <select name="free_query_operator[]">
                            <option value="{Galette\Filters\AdvancedMembersList::OP_EQUALS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_EQUALS')} selected="selected"{/if}>{_T string="is"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_BEFORE}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_BEFORE')} selected="selected"{/if}>{_T string="before"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_AFTER}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_AFTER')} selected="selected"{/if}>{_T string="after"}</option>
                        </select>
                        <input type="text" name="free_text[]" value="{$fs.search|date_format:{_T string="Y-m-d"}}" class="modif_date" maxlength="10" size="10"/>
                        <span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>
    {elseif $type eq constant('Galette\Entity\DynamicFields::BOOLEAN')}
                        <select name="free_query_operator[]">
                            <option value="{Galette\Filters\AdvancedMembersList::OP_EQUALS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_EQUALS')} selected="selected"{/if}>{_T string="is"}</option>
                        </select>
                        <input type="radio" name="free_text[]" id="free_text_yes" value="1"{if $fs.search eq 1} checked="checked"{/if}/><label for="free_text_yes">{_T string="Yes"}</label><input type="radio" name="free_text[]" id="free_text_no" value="0"{if $fs.search eq 0} checked="checked"{/if}/><label for="free_text_no">{_T string="No"}</label>
    {else}
                        <select name="free_query_operator[]">
                            <option value="{Galette\Filters\AdvancedMembersList::OP_EQUALS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_EQUALS')} selected="selected"{/if}>{_T string="is"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_CONTAINS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_CONTAINS')} selected="selected"{/if}>{_T string="contains"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_NOT_EQUALS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_NOT_EQUALS')} selected="selected"{/if}>{_T string="is not"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_NOT_CONTAINS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_NOT_CONTAINS')} selected="selected"{/if}>{_T string="do not contains"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_STARTS_WITH}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_STARTS_WITH')} selected="selected"{/if}>{_T string="starts with"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_ENDS_WITH}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_ENDS_WITH')} selected="selected"{/if}>{_T string="ends with"}</option>
                        </select>
                        <input type="text" name="free_text[]" value="{$fs.search}"{if $type eq constant('Galette\Entity\DynamicFields::TEXT')} class="large"{/if}/>
    {/if}
            </span>
                        <a class="fright clearfilter" href="#" title="{_T string="Remove criteria"}">{_T string="Remove criteria"}</a>
                    </li>
{/foreach}
                </ul>
            </fieldset>
{* This one will be available later
{if $login->isAdmin()}
            <fieldset class="cssform large">
                <legend class="ui-state-active ui-corner-top">{_T string="Expert search"}</legend>
                <div>
                    <p id="warningbox"><strong>{_T string="Be extremely careful when using this one!"}</strong><br/>{_T string="If the following is not empty, all others filters will be ignored."}</p>
                    <p>
                        <label class="bline" for="sql_where">{_T string="SQL query"}</label>
                        <textarea name="sql" id="sql"></textarea><br/>
                        <span class="exemple">{_T string="If your query does not begin with a 'SELECT' statement, it will automatically be added."}</span>
                    </p>
                </div>
            </fieldset>
{/if}*}
            <div class="center">
                <input type="hidden" name="advanced_filtering" value="true" />
                <input type="submit" class="inline" value="{_T string="Filter"}"/>
                <input type="submit" name="clear_adv_filter" class="inline" value="{_T string="Clear filter"}"/>
            </div>
        </form>
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            var _operators = {
                op_equals: { id: {Galette\Filters\AdvancedMembersList::OP_EQUALS}, name: "{_T string='is'}" },
                op_contains: { id: {Galette\Filters\AdvancedMembersList::OP_CONTAINS}, name: "{_T string='contains'}" },
                op_not_equals: { id: {Galette\Filters\AdvancedMembersList::OP_NOT_EQUALS}, name: "{_T string='is not'}" },
                op_not_contains: { id: {Galette\Filters\AdvancedMembersList::OP_NOT_CONTAINS}, name: "{_T string='do not contains'}" },
                op_starts_with: { id: {Galette\Filters\AdvancedMembersList::OP_STARTS_WITH}, name: "{_T string='starts with'}" },
                op_ends_with: { id: {Galette\Filters\AdvancedMembersList::OP_ENDS_WITH}, name: "{_T string='ends with'}" },
                op_before: { id: {Galette\Filters\AdvancedMembersList::OP_BEFORE}, name: "{_T string='before'}" },
                op_after: { id: {Galette\Filters\AdvancedMembersList::OP_AFTER}, name: "{_T string='after'}" },
            };

            var _fields = {
{foreach $search_fields as $field}
    {if $field@key|strpos:'date_' === 0 or $field@key eq 'ddn_adh'}
        {assign var=type value=constant('Galette\Entity\DynamicFields::DATE')}
    {else}
        {assign var=type value=constant('Galette\Entity\DynamicFields::LINE')}
    {/if}
                {$field@key}: { type:'{$type}' },
{/foreach}
{foreach $dynamic_fields as $field}
    {if $field.field_type eq constant('Galette\Entity\DynamicFields::SEPARATOR')}
        {continue}
    {else}
                dyn_{$field.field_id}: { type:'{$field.field_type}' },
    {/if}
{/foreach}
            };

            var _newFilter = function(elt) {
                elt.find('span').html('');
                elt.find('select.operator_selector').val(0);
                elt.find('select.field_selector').val('');
            }
            var _rmFilter = function(elt) {
                if ( !elt ) {
                    elt = $('li');
                }
                elt.find('.clearfilter').click(function(){
                    var _this = $(this);
                    if ( _this.parents('ul').find('li').length > 1 ) {
                        _this.parent('li').remove();
                    } else {
                        _newFilter(_this.parent('li'));
                    }
                    return false;
                });
            }
            var _getOperatorSelector = function(list) {
                var _options = '';
                for (var i = 0; i < list.length; i++) {
                    var _operator = _operators[list[i]];
                    _options += '<option value="' + _operator.id + '">' + _operator.name + '</option>';
                }
                return '<select name="free_query_operator[]">' + _options + '</select>';
            }

            var _datePickers = function() {
                $.datepicker.setDefaults($.datepicker.regional['{$galette_lang}']);
                $('.modif_date').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    buttonImage: '{base_url}/{$template_subdir}images/calendar.png',
                    buttonImageOnly: true,
                    maxDate: '-0d',
                    yearRange: 'c-10:c+0',
                    buttonText: '{_T string="Select a date" escape="js"}'
                });
                $('.due_date').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    buttonImage: '{base_url}/{$template_subdir}images/calendar.png',
                    buttonImageOnly: true,
                    yearRange: 'c-10:c+5',
                    buttonText: '{_T string="Select a date" escape="js"}'
                });
            }

            $(function(){
                _collapsibleFieldsets();
                _initSortable();
                _datePickers();

                $('#btnadd_small').click(function(){
                    var _ul = $('#fs_sortable');
                    var _new = _ul.find('li').last().clone(true);
                    _newFilter(_new);
                    _rmFilter(_new);
                    _ul.append(_new);
                    _fieldsInSortable();
                    return false;
                });

                $('.field_selector').change(function () {
                    var _field_id = $(this).val();
                    var _field    = _fields[_field_id];
                    var _type     = _field.type;

                    if (!_type) {
                        return false;
                    }

                    var _html;
                    switch(_type) {
                        case '{constant('Galette\Entity\DynamicFields::BOOLEAN')}':
                            _html  = _getOperatorSelector(['op_equals']);

                            _html += '<input type="radio" name="free_text[]" id="free_text_yes" value="1"{if $fs.search eq 1} checked="checked"{/if}/><label for="free_text_yes">{_T string="Yes"}</label><input type="radio" name="free_text[]" id="free_text_no" value="0"{if $fs.search eq 0} checked="checked"{/if}/><label for="free_text_no">{_T string="No"}</label>';
                            break;
                        case '{constant('Galette\Entity\DynamicFields::CHOICE')}':
                            _html = _getOperatorSelector(['op_equals', 'op_not_equals']);
                            var _options = '';
                            for (var i = 0; i < _field.values.length; i++) {
                                _options += '<option value="' + i + '">' + _field.values[i] + '</option>';
                            }
                            _html += '<select name="free_text[]">' + _options + '</select>';
                            break;
                        case '{constant('Galette\Entity\DynamicFields::DATE')}':
                            _html  = _getOperatorSelector(['op_equals', 'op_before', 'op_after']);
                            _html += '<input type="text" name="free_text[]" class="modif_date" maxlength="10" size="10"/>';
                            _html += '<span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>';
                            break;
                        default:
                            _html  = _getOperatorSelector(['op_equals', 'op_contains', 'op_not_equals', 'op_not_contains', 'op_starts_with', 'op_ends_with']);
                            _html += '<input type="text" name="free_text[]"';
                            if (_type == 'text') {
                                _html += ' class="large"';
                            }
                            _html += '/>';
                            break;

                    }
                    _html += '<input type="hidden" name="free_type[]" value="' + _type + '"/>';
                    $(this).parent().find('span').html(_html);
                    _datePickers();
                    _fieldsInSortable();
                });

                _rmFilter();
            });
        </script>
{/block}
