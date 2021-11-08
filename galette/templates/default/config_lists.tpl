{extends file="page.tpl"}
{block name="content"}
    <form action="{path_for name="storeListFields" data=["table" => $table]}" method="post" id="config_fields_form">
    <div id="members_tab" class="cssform">
        <ul id="listed_fields" class="fields_list notype connectedSortable">
            <li class="listing center">
                {_T string="Fields in list"}
                {*<span class="label">{_T string="Field name"}</span>
                <span class="access">{_T string="Permissions"}</span>*}
            </li>
    {foreach key=col item=field from=$listed_fields name=fields_list}
            {assign var='fid' value=$field.field_id}
            <li class="tbl_line_{if $smarty.foreach.fields_list.iteration % 2 eq 0}even{else}odd{/if}{if $fid eq 'id_adh' or $fid eq 'list_adh_name'} nosort ui-state-disabled{/if}">
                <span class="label" data-title="{_T string="Field name"}">
                    <input type="hidden" name="fields[]" value="{$fid}"/>
                    {$field.label}
                </span>
                <span data-title="{_T string="Permissions"}" class="access" title="{_T string="Change '%field' permissions" pattern="/%field/" replace=$field.label}">
                    {if $field.visible eq constant('Galette\Entity\FieldsConfig::NOBODY')}{_T string="Inaccessible"}{/if}
                    {if $field.visible eq constant('Galette\Entity\FieldsConfig::ADMIN')}{_T string="Administrator"}{/if}
                    {if $field.visible eq constant('Galette\Entity\FieldsConfig::STAFF')}{_T string="Staff member"}{/if}
                    {if $field.visible eq constant('Galette\Entity\FieldsConfig::MANAGER')}{_T string="Group manager"}{/if}
                    {if $field.visible eq constant('Galette\Entity\FieldsConfig::USER_READ')}{_T string="Read only"}{/if}
                    {if $field.visible eq constant('Galette\Entity\FieldsConfig::USER_WRITE')}{_T string="Read/Write"}{/if}
                </span>
            </li>
    {/foreach}
        </ul>
        <ul id="remaining_fields" class="fields_list notype connectedSortable">
            <li class="listing center">
                {_T string="Available fields"}
                {*<span class="label">{_T string="Field name"}</span>
                <span class="access">{_T string="Permissions"}</span>*}
            </li>
    {foreach key=col item=field from=$remaining_fields name=rfields_list}
            {assign var='fid' value=$field.field_id}
            <li class="tbl_line_{if $smarty.foreach.rfields_list.iteration % 2 eq 0}even{else}odd{/if}">
                <span class="label" data-title="{_T string="Field name"}">
                    <input type="hidden" name="rfields[]" value="{$fid}"/>
                    {$field.label}
                </span>
                <span data-title="{_T string="Permissions"}" class="access" title="{_T string="Change '%field' permissions" pattern="/%field/" replace=$field.label}">
                    {if $field.visible eq constant('Galette\Entity\FieldsConfig::NOBODY')}{_T string="Inaccessible"}{/if}
                    {if $field.visible eq constant('Galette\Entity\FieldsConfig::ADMIN')}{_T string="Administrator"}{/if}
                    {if $field.visible eq constant('Galette\Entity\FieldsConfig::STAFF')}{_T string="Staff member"}{/if}
                    {if $field.visible eq constant('Galette\Entity\FieldsConfig::MANAGER')}{_T string="Group manager"}{/if}
                    {if $field.visible eq constant('Galette\Entity\FieldsConfig::USER_READ')}{_T string="Read only"}{/if}
                    {if $field.visible eq constant('Galette\Entity\FieldsConfig::USER_WRITE')}{_T string="Read/Write"}{/if}
                </span>
            </li>
    {/foreach}
        </ul>

    </div>
        <div class="button-container">
            <button type="submit" class="action">
                <i class="fas fa-save fa-fw"></i> {_T string="Save"}
            </button>
        </div>
        {include file="forms_types/csrf.tpl"}
    </form>
{/block}

{block name="javascripts"}
    <script type="text/javascript">
        var _initSortable = function(){
            $('.fields_list').sortable({
                items: 'li:not(.listing,.nosort)',
                connectWith: '.connectedSortable',
                update: function(event, ui) {
                    // When sort is updated, we must check for the newer category item belongs to
                    var _item = $($(ui.item[0])[0]);
                    var _parent = _item.parent('ul.fields_list');
                    var _current = _parent.attr('id');
                    if (_current == 'remaining_fields') {
                        _item.find('input[name=fields\\[\\]]').attr('name', 'rfields[]');
                    } else {
                        _item.find('input[name=rfields\\[\\]]').attr('name', 'fields[]');
                    }
                }
            }).disableSelection();
        }

        $(function() {
            _initSortable();
        });
    </script>
{/block}
