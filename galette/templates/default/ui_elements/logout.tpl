{if isset($ui)}
    {if $ui eq 'item'}
       {assign var="component_classes" value="item"}
    {elseif $ui eq 'segment'}
       {assign var="component_classes" value="ui segment"}
    {/if}
{/if}

<div class="{$component_classes}">
    <div class="ui basic center aligned fitted segment">
        <span class="ui tiny header">{$login->loggedInAs()}</span>
    </div>
    <a
        class="ui fluid red basic button"
        href="{if $login->isImpersonated()}{path_for name="unimpersonate"}{else}{path_for name="logout"}{/if}"
    >
        <i class="icon {if $login->isImpersonated()}user secret{else}sign out alt{/if}"></i>
        {_T string="Log off"}
    </a>
</div>
