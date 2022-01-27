<div class="field">
    <label>{_T string="Groups:"}</label>
    {if $login->isGroupManager()}
    <a class="ui icon button" id="btngroups">
        <i class="user tag icon" aria-hidden="true"></i>
        {_T string="Manage user's groups"}
    </a>
    {/if}
    {if $login->isAdmin() or $login->isStaff()}
    <a class="ui icon button" id="btnmanagedgroups">
        <i class="user shield icon" aria-hidden="true"></i>
        {_T string="Manage user's managed groups"}
    </a>
    {/if}
    <span id="usergroups_form">
    {if $member->id}
        {foreach from=$groups item=group}
            {if $member->isGroupMember($group->getName())}
            <input type="hidden" name="groups_adh[]" value="{$group->getId()}|{$group->getName()}"/>
            {/if}
        {/foreach}
    {/if}
    </span>
    {if $login->isAdmin() or $login->isStaff() or $login->isGroupManager()}
    <span id="managedgroups_form">
    {if $member->id}
        {foreach from=$groups item=group}
            {if $member->isGroupManager($group->getName())}
        <input type="hidden" name="groups_managed_adh[]" value="{$group->getId()}|{$group->getName()}"/>
            {/if}
        {/foreach}
    {/if}
    </span>
    {/if}
    {if $login->isAdmin() or $login->isStaff() or $login->isGroupManager()}<br/>{/if}
    <span id="usergroups">
    {if $member->id}
        {foreach from=$groups item=group name=groupsiterate}
            {if $member->isGroupMember($group->getName())}
                {if isset($isnotfirst)}, {else}<strong>{_T string="Member of:"}</strong>{/if}
                {assign var=isnotfirst value=true}
                {$group->getName()}
            {/if}
        {/foreach}
    {/if}
    </span>
    {if isset($isnotfirst)}<br/>{/if}
    <span id="managedgroups">
    {if $member->id}
        {foreach from=$groups item=group name=groupsmiterate}
            {if $member->isGroupManager($group->getName())}
                {if isset($isnotfirstm)}, {else}<strong>{_T string="Manager for:"}</strong>{/if}
                {assign var=isnotfirstm value=true}
                {$group->getName()}
            {/if}
        {/foreach}
    {/if}
    </span>
</div>
