        <form class="tabbed" action="gestion_groupes.php" method="post" enctype="multipart/form-data" id="group_form">
        <div id="group">
            <ul>
                <li><a href="#group_informations">{_T string="Informations"}</a></li>
                <li><a href="#group_managers">{_T string="Managers"}</a></li>
                <li><a href="#group_members">{_T string="Members"}</a></li>
            </ul>
            <fieldset class="cssform" id="group_informations">
                <legend >{_T string="Informations"}</legend>
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
                        <span>{if isset($pgroup)}{$pgroup->getName()}{/if}</span>
{else}
                        <label for="parent_group" class="bline">{_T string="Parent group:"}</label>
                        <select name="parent_group" id="parent_group">
                            <option value="">{_T string="None"}</option>
{foreach item=g from=$groups}
                            <option value="{$g->getId()}"{if isset($pgroup) and $pgroup->getId() eq $g->getId()} selected="selected"{/if}>{$g->getName()}</option>
{/foreach}
                        </select>
{/if}
                    </p>
                </div>
            </fieldset>
            <fieldset class="cssform" id="group_managers">
                <legend>{_T string="Managers"}</legend>
                <div>
                    {if $group}
                        {include file="group_persons.tpl" person_mode="managers" persons=$group->getManagers()}
                    {/if}
                </div>
           </fieldset>
            <fieldset class="cssform" id="group_members">
                <legend>
                    {_T string="Members"}
               </legend>
                <div>
                    {if $group}
                        {include file="group_persons.tpl" person_mode="members" persons=$group->getMembers()}
                    {/if}
                </div>
            </fieldset>
{if $login->isAdmin() or $login->isStaff()}
            <a href="#" class="button notext hidden" id="btnusers_small">{_T string="Manage members"}</a>
            <a href="#" class="button notext hidden" id="btnmanagers_small">{_T string="Manage managers"}</a>
{/if}
      </div>
        <div class="button-container">
            <input type="submit" name="valid" id="btnsave" value="{_T string="Save"}"/>
            <input type="submit" name="delete" id="delete" value="{_T string="Delete"}"/>
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
    });
</script>
