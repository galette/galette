{assign var="subgroups" value=$item->getGroups()}
{assign var="active" value=$item->getId() eq $current->getId() or isset($subgroups[$current->getId()])}
{assign var="active" value=isset($subgroups[$current->getId()])}
<div class="{if $active}active {/if}title" id="group_{$item->getId()}">
{if $item->getGroups()|@count > 0}
    <i class="dropdown icon"></i>
{else}
    <i class="empty icon"></i>
{/if}
    <a href="{if $login->isGroupManager($item->getId())}{path_for name="groups" data=["id" => $item->getId()]}{else}#{/if}">{$item->getName()}</a>
</div>
<div class="{if $active}active {/if}content">
{if $subgroups|@count > 0}
    <div class="accordion">
{foreach item=newitem from=$subgroups}
    {include file="group_tree_item.tpl" item=$newitem current=$current}
{/foreach}
    </div>{* /accordion *}
{/if}
</div>{* /content *}
