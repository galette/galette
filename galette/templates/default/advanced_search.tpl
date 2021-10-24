{extends file="page.tpl"}

{block name="content"}
        <form action="{path_for name="filter-memberslist"}" method="post" id="filtre">
            <fieldset class="cssform large">
                <legend class="ui-state-active ui-corner-top">{_T string="Simple search"}</legend>
                <div>
                    <p>
                        <label class="bline" for="filter_str">{_T string="Search:"}</label>
                        <input type="text" name="filter_str" id="filter_str" value="{$filters->filter_str}" placeholder="{_T string="Enter a value"}"/>&nbsp;
                        {_T string="in:"}&nbsp;
                        <select name="field_filter">
                            {html_options options=$field_filter_options selected=$filters->field_filter}
                        </select>
                    </p>
                    <p>
                        <label class="bline" for="membership_filter">{_T string="Membership status"}</label>
                        <select id="membership_filter" name="membership_filter">
                            {html_options options=$membership_filter_options selected=$filters->membership_filter}
                        </select>
                    </p>
                    <p>
                        <label class="bline" for="filter_account">{_T string="Account activity"}</label>
                        <select id="filter_account" name="filter_account">
                            {html_options options=$filter_accounts_options selected=$filters->filter_account}
                        </select>
                    </p>
                    <p>
                        <label class="bline" for="group_filter">{_T string="Member of group"}</label>
                        <select name="group_filter" id="group_filter">
                            <option value="0">{_T string="Select a group"}</option>
{foreach from=$filter_groups_options item=group}
                            <option value="{$group->getId()}"{if $filters->group_filter eq $group->getId()} selected="selected"{/if}>{$group->getIndentName()}</option>
{/foreach}
                        </select>
                    </p>
                    <p>
                        <span class="bline">{_T string="With email:"}</span>
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
                        <input id="birth_date_begin" name="birth_date_begin" type="text" class="birth_date" maxlength="10" size="10" value="{$filters->birth_date_begin}"/>
                        <label for="birth_date_end">{_T string="and"}</label>
                        <input id="birth_date_end" name="birth_date_end" type="text" class="birth_date" maxlength="10" size="10" value="{$filters->birth_date_end}"/>
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
                        <select name="status[]" id="status" multiple="multiple" class="nochosen">
                            {html_options options=$statuts selected=$filters->status}
                        </select>
                    </p>
                </div>
            </fieldset>
            <fieldset class="cssform large">
                <legend class="ui-state-active ui-corner-top">{_T string="Advanced groups search"} ({_T string="Experimental"})
                    <a
                        href="#"
                        id="addbutton_g"
                        class="tab-button tooltip"
                    >
                        <i class="fas fa-plus-square"></i>
                        <span class="sr-only">{_T string="Add new group search criteria"}</span>
                    </a>
                </legend>
                <select name="groups_logical_operator" class="operator_selector nochosen">
                  <option value="{Galette\Filters\AdvancedMembersList::OP_AND}"{if $filters->groups_search_log_op eq constant('Galette\Filters\AdvancedMembersList::OP_AND')} selected="selected"{/if}>{_T string="In all selected groups"}</option>
                  <option value="{Galette\Filters\AdvancedMembersList::OP_OR}"{if $filters->groups_search_log_op eq constant('Galette\Filters\AdvancedMembersList::OP_OR')} selected="selected"{/if}>{_T string="In any of selected groups"}</option>
                </select>
                <ul id="groups_search_list" class="fields_list">
                {foreach from=$filters->groups_search item=gs}
                         <li>
                            <select name="groups_search[]" class="group_selector nochosen">
                                    <option value="">{_T string="Select a group"}</option>
                                    {foreach from=$filter_groups_options item=group}
                                    <option value="{$group->getId()}"{if $gs.group eq $group->getId()} selected="selected"{/if}>{$group->getName()}</option>
                                    {/foreach}
                            </select>
                            <a
                                href="#"
                                class="fright tooltip delete delcriteria"
                            >
                                <i class="fas fa-trash-alt"></i>
                                <span class="sr-only">{_T string="Remove criteria"}</span>
                            </a>
                        </li>
                 {/foreach}
                 </ul>
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
{foreach $contrib_dynamics as $field}
    {assign var=fid value=$field->getId()}
    {if $field|is_a:'Galette\DynamicFields\Choice'}
        {assign var=rid value="cdsc_$fid"}
    {else}
        {assign var=rid value="cds_$fid"}
    {/if}
                    <p>
                        <label class="bline" for="cds{if $field|is_a:'Galette\DynamicFields\Choice'}c{/if}_{$field->getId()}">{$field->getName()}</label>
    {if $field|is_a:'Galette\DynamicFields\Line'}
                        <input type="text" name="cds_{$field->getId()}" id="cds_{$field->getId()}" value="{if isset($filters->contrib_dynamic.$rid)}{$filters->contrib_dynamic.$rid}{/if}" />
    {elseif $field|is_a:'Galette\DynamicFields\Text'}
                        <textarea name="cds_{$field->getId()}" id="cds_{$field->getId()}">{if isset($filters->contrib_dynamic.$rid)}{$filters->contrib_dynamic.$rid}{/if}</textarea>
    {elseif $field|is_a:'Galette\DynamicFields\Choice'}
                        <select name="cdsc_{$field->getId()}[]" id="cdsc_{$field->getId()}" multiple="multiple">
                            <option value="">{_T string="Select"}</option>
        {foreach $field->getValues() item=choice key=k}
                            <option value="{$k}"{if isset($cds.field) and  $cds.field eq $rid} selected="selected"{/if}>{$choice}</option>
        {/foreach}
                        </select>
    {/if}
                    </p>
{/foreach}
                </div>
            </fieldset>
            <fieldset class="cssform large">
                <legend class="ui-state-active ui-corner-top">
                    {_T string="Free search"}
                    <a
                        href="#"
                        id="addbutton"
                        class="tab-button tooltip"
                    >
                        <i class="fas fa-plus-square"></i>
                        <span class="sr-only">{_T string="Add new free search criteria"}</span>
                    </a>
                </legend>
                <ul id="fs_sortable" class="fields_list connectedSortable">
{foreach from=$filters->free_search item=fs}
                    <li>
                        <select name="free_logical_operator[]" class="operator_selector nochosen">
                            <option value="{Galette\Filters\AdvancedMembersList::OP_AND}"{if $fs.log_op eq constant('Galette\Filters\AdvancedMembersList::OP_AND')} selected="selected"{/if}>{_T string="and"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_OR}"{if $fs.log_op eq constant('Galette\Filters\AdvancedMembersList::OP_OR')} selected="selected"{/if}>{_T string="or"}</option>
                        </select>
                        <select name="free_field[]" class="field_selector nochosen">
                            <option value="">{_T string="Select a field"}</option>
    {foreach $search_fields as $field}
        {if $fs.field eq $field@key}
            {if $field@key|strpos:'date_' === 0 or $field@key eq 'ddn_adh'}
                {assign var=type value=constant('Galette\DynamicFields\DynamicField::DATE')}
            {else if $field@key === {Galette\Entity\Status::PK}}
                {assign var=type value=constant('Galette\DynamicFields\DynamicField::CHOICE')}
                {assign var=fvalues value=$statuts}
            {else if $field@key === 'sexe_adh'}
                {assign var=type value=constant('Galette\DynamicFields\DynamicField::CHOICE')}
                {assign var=fvalues value=[Galette\Entity\Adherent::NC => {_T string="Unspecified"}, Galette\Entity\Adherent::MAN => {_T string="Man"}, Galette\Entity\Adherent::WOMAN => {_T string="Woman"}]}
            {else}
                {assign var=type value=constant('Galette\DynamicFields\DynamicField::LINE')}
            {/if}
        {/if}
                            <option value="{$field@key}"{if $fs.field eq $field@key} selected="selected"{/if}>{$field.label}</option>
    {/foreach}
    {foreach $adh_dynamics as $field}
        {if $field|is_a:'Galette\DynamicFields\Separator'}
                {continue}
        {/if}
        {assign var=fid value=$field->getId()}
        {assign var=rid value="dyn_$fid"}
        {if $fs.field eq $rid}
            {assign var=cur_field value=$field}
        {/if}
                            <option value="dyn_{$field->getId()}"{if $fs.field eq $rid} selected="selected"{/if}>{$field->getName()}</option>
    {/foreach}
    {foreach from=$adh_socials item=$label key=$type}
        {assign var=rid value="socials_$type"}
        {if $fs.field eq $rid}
            {assign var=cur_field value=$type}
        {/if}
        <option value="socials_{$type}"{if $fs.field eq $rid} selected="selected"{/if}>{$label}</option>
    {/foreach}

                        </select>
    {* may not be defined *}
    {if !isset($cur_field)}{assign var=cur_field value=null}{/if}
    {if !isset($type)}{assign var=type value=null}{/if}
                        <span class="data">
                            <input type="hidden" name="free_type[]" value="{if isset($cur_field)}{$cur_field->getType()}{/if}"/>
    {if $cur_field|is_a:'Galette\DynamicFields\Choice' || $type eq constant('Galette\DynamicFields\DynamicField::CHOICE')}
                        <select name="free_query_operator[]" class="free_operator nochosen">
                            <option value="{Galette\Filters\AdvancedMembersList::OP_EQUALS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_EQUALS')} selected="selected"{/if}>{_T string="is"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_NOT_EQUALS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_NOT_EQUALS')} selected="selected"{/if}>{_T string="is not"}</option>
                        </select>
                        <select name="free_text[]" class="free_text nochosen">
        {if $cur_field|is_a:'Galette\DynamicFields\Choice'}
                        {html_options options=$cur_field->getValues() selected=$fs.search}
        {else}
                        {html_options options=$fvalues selected=$fs.search}
        {/if}
                        </select>
    {elseif $cur_field|is_a:'Galette\DynamicFields\Date' || $type == constant('Galette\DynamicFields\DynamicField::DATE')}
                        <select name="free_query_operator[]" class="free_operator nochosen">
                            <option value="{Galette\Filters\AdvancedMembersList::OP_EQUALS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_EQUALS')} selected="selected"{/if}>{_T string="is"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_BEFORE}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_BEFORE')} selected="selected"{/if}>{_T string="before"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_AFTER}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_AFTER')} selected="selected"{/if}>{_T string="after"}</option>
                        </select>
                        <input type="text" name="free_text[]" value="{$fs.search|date_format:{_T string="Y-m-d"}}" class="modif_date" maxlength="10" size="10"/>
                        <span class="exemple">{_T string="(yyyy-mm-dd format)"}</span>
    {elseif $cur_field|is_a:'Galette\DynamicFields\Boolean' || $type == constant('Galette\DynamicFields\DynamicField::BOOLEAN')}
                        <select name="free_query_operator[]" class="free_operator nochosen">
                            <option value="{Galette\Filters\AdvancedMembersList::OP_EQUALS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_EQUALS')} selected="selected"{/if}>{_T string="is"}</option>
                        </select>
                        <input type="radio" name="free_text[]" id="free_text_yes" value="1"{if $fs.search eq 1} checked="checked"{/if}/><label for="free_text_yes">{_T string="Yes"}</label>
                        <input type="radio" name="free_text[]" id="free_text_no" value="0"{if $fs.search eq 0} checked="checked"{/if}/><label for="free_text_no">{_T string="No"}</label>
    {else}
                        <select name="free_query_operator[]" class="free_operator nochosen">
                            <option value="{Galette\Filters\AdvancedMembersList::OP_EQUALS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_EQUALS')} selected="selected"{/if}>{_T string="is"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_CONTAINS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_CONTAINS')} selected="selected"{/if}>{_T string="contains"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_NOT_EQUALS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_NOT_EQUALS')} selected="selected"{/if}>{_T string="is not"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_NOT_CONTAINS}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_NOT_CONTAINS')} selected="selected"{/if}>{_T string="do not contains"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_STARTS_WITH}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_STARTS_WITH')} selected="selected"{/if}>{_T string="starts with"}</option>
                            <option value="{Galette\Filters\AdvancedMembersList::OP_ENDS_WITH}"{if $fs.qry_op eq constant('Galette\Filters\AdvancedMembersList::OP_ENDS_WITH')} selected="selected"{/if}>{_T string="ends with"}</option>
                        </select>
                        <input type="text" name="free_text[]" value="{$fs.search}"{if $cur_field|is_a:'Galette\DynamicFields\Text'} class="large"{/if}/>
    {/if}
            </span>
                        <a
                            href="#"
                            class="fright tooltip delete delcriteria"
                        >
                            <i class="fas fa-trash-alt"></i>
                            <span class="sr-only">{_T string="Remove criteria"}</span>
                        </a>
                    </li>
{/foreach}
                </ul>
            </fieldset>
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
        {assign var=type value=constant('Galette\DynamicFields\DynamicField::DATE')}
    {else if $field@key === {Galette\Entity\Status::PK}}
        {assign var=type value=constant('Galette\DynamicFields\DynamicField::CHOICE')}
        {assign var=fvalues value=$statuts}
    {else if $field@key === 'sexe_adh'}
        {assign var=type value=constant('Galette\DynamicFields\DynamicField::CHOICE')}
        {assign var=fvalues value=[Galette\Entity\Adherent::NC => {_T string="Unspecified"}, Galette\Entity\Adherent::MAN => {_T string="Man"}, Galette\Entity\Adherent::WOMAN => {_T string="Woman"}]}
    {else}
        {assign var=type value=constant('Galette\DynamicFields\DynamicField::LINE')}
    {/if}
                {$field@key}: { type:'{$type}'{if isset($fvalues)}, values: {$fvalues|@json_encode}{/if} },
{/foreach}
{foreach $adh_dynamics as $field}
    {if $field|is_a:'Galette\DynamicFields\Separator'}
        {continue}
    {else if $field|is_a:'Galette\DynamicFields\Choice'}
                dyn_{$field->getId()}: { type:'{$field->getType()}', values: {$field->getValues()|@json_encode} },
    {else}
                dyn_{$field->getId()}: { type:'{$field->getType()}' },
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
                elt.find('.delcriteria').click(function(){
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
                return '<select name="free_query_operator[]" class="free_operator newselectize">' + _options + '</select>';
            }

            var _datePickers = function() {
                $('.modif_date').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    maxDate: '-0d',
                    yearRange: 'c-10:c+0',
                    buttonText: '<i class="far fa-calendar-alt"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
                });
                $('.due_date').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    yearRange: 'c-10:c+5',
                    buttonText: '<i class="far fa-calendar-alt"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
                });
            }

            var _selectize = function(selector) {
                if ( !selector ) {
                    selector = '.operator_selector,.field_selector,.free_operator,.free_text,.group_selector';
                }

                $(selector).selectize({
                    maxItems: 1
                });
            }

            var _initFieldSelector = function(parent) {
                if (typeof parent == 'undefined') {
                    parent = '';
                }
                $(parent + '.field_selector').change(function () {
                    var _field_id = $(this).val();
                    var _field    = _fields[_field_id];
                    var _type     = _field.type;

                    if (!_type) {
                        return false;
                    }

                    var _html;
                    switch(_type) {
                        case '{constant('Galette\DynamicFields\DynamicField::BOOLEAN')}':
                            _html  = _getOperatorSelector(['op_equals']);

                            _html += '<input type="radio" name="free_text[]" id="free_text_yes" value="1"{if $fs.search eq 1} checked="checked"{/if}/><label for="free_text_yes">{_T string="Yes"}</label><input type="radio" name="free_text[]" id="free_text_no" value="0"{if $fs.search eq 0} checked="checked"{/if}/><label for="free_text_no">{_T string="No"}</label>';
                            break;
                        case '{constant('Galette\DynamicFields\DynamicField::CHOICE')}':
                            _html = _getOperatorSelector(['op_equals', 'op_not_equals']);
                            var _options = '';
                            if (Array.isArray(_field.values)) {
                                for (var i = 0; i < _field.values.length; i++) {
                                    _options += '<option value="' + i + '">' + _field.values[i] + '</option>';
                                }
                            } else {
                                for (key in _field.values) {
                                    _options += '<option value="' + key + '">' + _field.values[key] + '</option>';
                                }
                            }
                            _html += '<select name="free_text[]" class="newselectize">' + _options + '</select>';
                            break;
                        case '{constant('Galette\DynamicFields\DynamicField::DATE')}':
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
                    $(this).parent().find('span.data').html(_html);
                    _selectize('.newselectize');
                    $('.newselectize').removeClass('newselectize');
                    _datePickers();
                    _fieldsInSortable();
                });
                _rmFilter();
            }

            $(function(){
                _collapsibleFieldsets();
                _initSortable();
                _datePickers();
                _selectize();

               $('#addbutton_g').click(function(){
                    $('.operator_selector,.group_selector').each(function(){ // do this for every select with the 'combobox' class
                        if ($(this)[0].selectize) { // requires [0] to select the proper object
                            var value = $(this).val(); // store the current value of the select/input
                            $(this)[0].selectize.destroy(); // destroys selectize()
                            $(this).val(value);  // set back the value of the select/input
                        }
                    });

                    $('#groups_search_list li:first')
                            .clone() // copy
                            .insertAfter('#groups_search_list li:last'); // where
                    _selectize();
                    return false;
                });

                $('#addbutton').click(function(){
                    $('.operator_selector,.field_selector,.free_operator').each(function(){ // do this for every select with the 'combobox' class
                        if ($(this)[0].selectize) { // requires [0] to select the proper object
                            var value = $(this).val(); // store the current value of the select/input
                            $(this)[0].selectize.destroy(); // destroys selectize()
                            $(this).val(value);  // set back the value of the select/input
                        }
                    });
                    $('#fs_sortable li:first')
                            .clone() // copy
                            .insertAfter('#fs_sortable li:last'); // where
                    _selectize();
                    _datePickers();
                    _fieldsInSortable();
                    _initFieldSelector();
                    return false;
                });

                _initFieldSelector();

                $('.birth_date').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    maxDate: '-0d',
                    yearRange: '-200:+0',
                    buttonText: '<i class="far fa-calendar-alt"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
                });
            });
        </script>
{/block}
