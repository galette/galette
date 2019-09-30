{assign var="canEdit" value=$login->isGroupManager() && $preferences->pref_bool_groupsmanagers_edit_groups || $login->isAdmin() || $login->isStaff()}
{assign var="canExport" value=$login->isGroupManager() && $preferences->pref_bool_groupsmanagers_exports || $login->isAdmin() || $login->isStaff()}
        <form class="tabbed ui form" action="{path_for name="doEditGroup" data=["id" => $group->getId()]}" method="post" enctype="multipart/form-data" id="group_form">
        <div id="group">
            <ul>
                <li><a href="#group_information">{_T string="Information"}</a></li>
                <li><a href="#group_managers">{_T string="Managers"}</a></li>
                <li><a href="#group_members">{_T string="Members"}</a></li>
            </ul>
            <fieldset class="cssform" id="group_information">
				<div class="ui basic segment">
                	<div class="ui small red message">{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></div>
{if $group->getId() }
		            <div class="inline field">
		                <label>{_T string="Creation date:"}</label>
		                <span>{$group->getCreationDate()}</span>
		            </div>
{/if}
		            <div class="inline field required">
{if $canEdit}
		                <label for="group_name">{_T string="Name:"}</label>
		                <input type="text" name="group_name" id="group_name" value="{$group->getName()}" maxlength="20" required/>
		            </div>
{else}
                        <span class="bline">{_T string="Name:"}</span>
                        {$group->getName()}
{/if}


{if $group->getParentGroup()}
    {assign var='pgroup' value=$group->getParentGroup()}
{/if}
                	<div class="inline field">
{if $canEdit}
		                <label for="parent_group">{_T string="Parent group:"}</label>
		                <select name="parent_group" id="parent_group" class="ui search dropdown nochosen">
                        	<option value="">{_T string="None"}</option>
    {foreach item=g from=$groups}
        {if $group->canSetParentGroup($g)}
                        	<option value="{$g->getId()}"{if isset($pgroup) and $pgroup->getId() eq $g->getId()} selected="selected"{/if}>{$g->getIndentName()}</option>
        {/if}
    {/foreach}
                    	</select>
{else}
                        <span class="bline">{_T string="Parent group:"}</span>
                        <span>
    {if isset($pgroup)}
                            {$pgroup->getName()}
                            <input type="hidden" name="parent_group" value="{$pgroup->getId()}"/>
    {else}
        -
    {/if}
                        </span>
{/if}
                	</div>
				</div>
            </fieldset>
            <fieldset class="cssform" id="group_managers">
				<div class="ui basic segment">
                {assign var="managers" value=$group->getManagers()}
		            <div class="ui basic fitted segment">
		                <div class="ui label">{_T string="Managers"} ({$managers|@count})</div>
		            </div>
                    {if $group}
                        {include file="group_persons.tpl" person_mode="managers" persons=$managers}
                    {/if}
                </div>
            </fieldset>
            <fieldset class="cssform" id="group_members">
                <div class="ui basic segment">
		            {assign var="members" value=$group->getMembers()}
		            <div class="ui basic fitted segment">
		                <div class="ui label">{_T string="Members"} ({$members|@count})</div>
		            </div>
                    {if $group}
                        {include file="group_persons.tpl" person_mode="members" persons=$members}
                    {/if}
                </div>
            </fieldset>
{if $login->isAdmin() or $login->isStaff()}
            <a href="#" class="ui compact icon button tab-button hidden tooltip" id="btnusers_small"><i class="user icon" aria-hidden="true"></i> <span class="sr-only">{_T string="Manage members"}</span></a>
            <a href="#" class="ui compact icon button tab-button hidden tooltip" id="btnmanagers_small"><i class="user shield icon" aria-hidden="true"></i> <span class="sr-only">{_T string="Manage managers"}</span></a>
{/if}
            <div class="ui basic segment button-container">
                <button type="submit" name="valid" class="ui labeled icon button action">
{if $canEdit}
                    <i class="save icon"></i> {_T string="Save"}
                </button>
            <input type="hidden" name="id_group" id="id_group" value="{$group->getId()}"/>
            {include file="forms_types/csrf.tpl"}
{/if}
    {if $login->isAdmin() or $login->isStaff()}
                <a class="ui labeled icon button delete" id="delete" href="{path_for name="removeGroup" data=["id" => $group->getId()]}">
                    <i class="trash alt icon"></i>
                    {_T string="Delete"}
                </a>
    {/if}
{if $canExport}
                <a href="{path_for name="pdf_groups" data=["id" => $group->getId()]}" class="ui labeled icon button tooltip" title="{_T string="Current group (and attached people) as PDF"}">
                    <i class="file pdf icon" aria-hidden="true"></i>
                    {_T string="Group PDF"}
                </a>
{/if}
            </div>
            </div>
{if $canEdit}
{/if}
        </form>
<script type="text/javascript">
    $(function() {
        {* Tabs *}
        $('#group').tabs({
            activate: function(event, ui) {
{if $login->isAdmin() or $login->isStaff()}
                var _id = ui.newPanel[0].id.substring(6);
                var _btnuid = '#btnusers_small';
                var _btnmid = '#btnmanagers_small';
                if ( _id == 'managers'  ) {
                    $(_btnmid).removeClass('hidden');
                    if ( !$(_btnuid).hasClass('hidden') ) {
                        $(_btnuid).addClass('hidden');
                    }
                } else if ( _id == 'members' ) {
                     $(_btnuid).removeClass('hidden');
                    if ( !$(_btnmid).hasClass('hidden') ) {
                        $(_btnmid).addClass('hidden');
                    }
               } else {
                    if ( !$(_btnuid).hasClass('hidden') ) {
                        $(_btnuid).addClass('hidden');
                    }
                    if ( !$(_btnmid).hasClass('hidden') ) {
                        $(_btnmid).addClass('hidden');
                    }
               }
{/if}
            }
        });
        {include file="js_removal.tpl"}
    });
</script>
