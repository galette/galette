<aside class="ui computer only toc">
    <div class="ui basic center aligned segment">
        <img src="{path_for name="logo"}" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="{$preferences->pref_nom}" class="icon"/>
        <div class="ui block huge brand header">
            {$preferences->pref_nom}
            {if $preferences->pref_slogan}<div class="sub tiny header">{$preferences->pref_slogan}</div>{/if}
        </div>
    </div>

    {include file="ui_elements/modes.tpl"}

    <div class="ui vertical accordion menu">
        {include file="navigation/navigation_items.tpl"}
    </div>

{if $login->isLogged()}
    {include
        file="ui_elements/logout.tpl"
        ui="segment"
    }
{/if}

</aside>
