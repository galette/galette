{if isset($navigate) and $navigate|@count != 0}
    <nav class="ui mini pagination menu right floated">
        {if isset($navigate.prev)}
                <a
                        href="{if isset($navigate.prev)}{path_for name="member" data=["id" => $navigate.prev]}{else}#{/if}"
                        class="{if !isset($navigate.prev)} disabled{/if} item"
                        title="{_T string="Previous"|escape}"
                >
                    <i class="step backward icon"></i>
                    <span class="sr-only">{_T string="Previous"}</span>
                </a>
        {/if}
        <div class="disabled item">{$navigate.pos} / {$navigate.count}</div>
        {if isset($navigate.next)}
                <a
                        href="{if isset($navigate.next)}{path_for name="member" data=["id" => $navigate.next]}{else}#{/if}"
                        class="{if !isset($navigate.next)} disabled{/if} item"
                        title="{_T string="Next"|escape}"
                >
                    <span class="sr-only">{_T string="Next"}</span>
                    <i class="step forward icon"></i>
                </a>
        {/if}
    </nav>
{/if}
