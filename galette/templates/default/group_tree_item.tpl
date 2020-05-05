    <li id="group_{$item->getId()}">
        <a href="{if $login->isGroupManager($item->getId())}{path_for name="groups" data=["id" => $item->getId()]}{else}#{/if}"{if $group->getId() eq $g->getId()} class="jstree-clicked"{/if}">{$item->getName()}</a>
    {if $item->getGroups()|@count > 0}
        <ul>
        {foreach item=newitem from=$item->getGroups()}
            {include file="group_tree_item.tpl" item=$newitem}
        {/foreach}
        </ul>
    {/if}
    </li>
