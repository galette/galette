    <li id="group_{$item->getId()}"{if $group->getId() eq $item->getId()} class="jstree-open"{/if}>
        <a
            href="{if $login->isGroupManager($item->getId())}{path_for name="groups" data=["id" => $item->getId()]}{else}#{/if}"
            class="{if $group->getId() eq $item->getId()}jstree-clicked"{/if} {if !$login->isGroupManager($item->getId())} jstree-disabled{/if}"
        >
            {$item->getName()}
        </a>
    {if $item->getGroups()|@count > 0}
        <ul>
        {foreach item=newitem from=$item->getGroups()}
            {include file="group_tree_item.tpl" item=$newitem}
        {/foreach}
        </ul>
    {/if}
    </li>
