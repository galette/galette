        <form class="tabbed" action="{path_for name="doEditGroup" data=["id" => $group->getId()]}" method="post" enctype="multipart/form-data" id="group_form">
        <div id="group">
            <ul>
                <li><a href="#group_information">{_T string="Information"}</a></li>
                <li><a href="#group_managers">{_T string="Managers"}</a></li>
                <li><a href="#group_members">{_T string="Members"}</a></li>
            </ul>
            <fieldset class="cssform" id="group_information">
                <legend >{_T string="Information"}</legend>
                <div>
{if $group->getId() }
                    <p>
                        <span class="bline">{_T string="Creation date:"}</span>
                        <span>{$group->getCreationDate()}</span>
                    </p>
{/if}
                    <p>
                        <label for="group_name" class="bline">{_T string="Name:"}</label>
                        <input type="text" name="group_name" id="group_name" value="{$group->getName()}" maxlength="20" required/>
                    </p>
{if $group->getParentGroup()}
    {assign var='pgroup' value=$group->getParentGroup()}
{/if}
                    <p>
{if !$login->isAdmin() && !$login->isStaff()}
                        <span class="bline">{_T string="Parent group:"}</span>
                        <span>
    {if isset($pgroup)}
                            {$pgroup->getName()}
                            <input type="hidden" name="parent_group" value="{$pgroup->getId()}"/>
    {/if}
                        </span>
{else}
                        <label for="parent_group" class="bline">{_T string="Parent group:"}</label>
                        <select name="parent_group" id="parent_group">
                            <option value="">{_T string="None"}</option>
{foreach item=g from=$groups}
    {if $group->canSetParentGroup($g)}
                            <option value="{$g->getId()}"{if isset($pgroup) and $pgroup->getId() eq $g->getId()} selected="selected"{/if}>{$g->getIndentName()}</option>
    {/if}
{/foreach}
                        </select>
{/if}
                    </p>
                </div>
            </fieldset>
            <fieldset class="cssform" id="group_managers">
                {assign var="managers" value=$group->getManagers()}
                <legend>
                    {_T string="Managers"} ({$managers|@count})
                </legend>
                <div>
                    {if $group}
                        {include file="group_persons.tpl" person_mode="managers" persons=$managers}
                    {/if}
                </div>
           </fieldset>
            <fieldset class="cssform" id="group_members">
                {assign var="members" value=$group->getMembers()}
                <legend>
                    {_T string="Members"} ({$members|@count})
               </legend>
                <div>
                    {if $group}
                        {include file="group_persons.tpl" person_mode="members" persons=$members}
                    {/if}
                </div>
            </fieldset>
{if $login->isAdmin() or $login->isStaff()}
            <a href="#" class="button tab-button hidden tooltip" id="btnusers_small"><i class="fas fa-user" aria-hidden="true"></i> <span class="sr-only">{_T string="Manage members"}</span></a>
            <a href="#" class="button tab-button hidden tooltip" id="btnmanagers_small"><i class="fas fa-user-shield" aria-hidden="true"></i> <span class="sr-only">{_T string="Manage managers"}</span></a>
{/if}
      </div>
        <div class="button-container">
            <button type="submit" name="valid" class="button action">
                <i class="fas fa-save fa-fw"></i> {_T string="Save"}
            </button>
{if $login->isAdmin() or $login->isStaff()}
            <a class="button delete" id="delete" href="{path_for name="removeGroup" data=["id" => $group->getId()]}">
                <i class="fas fa-trash-alt fa-fw"></i>
                {_T string="Delete"}
            </a>
{/if}
            <a href="{path_for name="pdf_groups" data=["id" => $group->getId()]}" class="button tooltip" title="{_T string="Current group (and attached people) as PDF"}">
                <i class="fas fa-file-pdf" aria-hidden="true"></i>
                {_T string="Group PDF"}
            </a>
            <input type="hidden" name="id_group" id="id_group" value="{$group->getId()}"/>
        </div>
        <p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
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
