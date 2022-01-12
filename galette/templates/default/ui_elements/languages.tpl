{if isset($ui)}
    {if $ui eq 'item'}
       {assign var="component_classes" value="language item"}
       {assign var="content_classes" value="content"}
       {assign var="header" value=true}
    {elseif $ui eq 'dropdown'}
       {assign var="component_classes" value="language ui dropdown right-aligned item"}
       {assign var="content_classes" value="menu"}
       {assign var="header" value=false}
    {/if}
{/if}

    <div class="{$component_classes}" title="{_T string="Choose your language"}">
{if $header eq true}
        <div class="image header title">
{/if}
            <i class="icon language" aria-hidden="true"></i>
            <span>{$galette_lang}</span>
            <i class="dropdown icon"></i>
{if $header eq true}
        </div>
{/if}
        <div class="{$content_classes}">
{foreach item=langue from=$languages}
    {if $langue->getAbbrev() neq $galette_lang}
            <a href="?ui_pref_lang={$langue->getID()}"
               title="{_T string="Switch locale to '%locale'" pattern="/%locale/" replace=$langue->getName()}"
               class="item"
            >
                {$langue->getName()} <span>({$langue->getAbbrev()})</span>
            </a>
    {/if}
{/foreach}
        </div>
    </div>
