    <li id="group_{$item->getId()}">
        <a href="{if $login->isGroupManager($item->getId())}gestion_groupes.php?id_group={$item->getId()}{else}#{/if}">{$item->getName()}</a>
    {if $item->getGroups()|@count > 0}
        <ul>
        {foreach item=newitem from=$item->getGroups()}
            {include file="group_tree_item.tpl" item=$newitem}
        {/foreach}
        </ul>
    {/if}
    </li>