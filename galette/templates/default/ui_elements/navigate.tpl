{if isset($navigate) and $navigate|@count != 0}
    <nav class="ui item very mini buttons right floated">
        <a
                href="{if isset($navigate.prev)}{path_for name="member" data=["id" => $navigate.prev]}{else}#{/if}"
                class="ui icon button tooltip{if !isset($navigate.prev)} disabled{/if}"
                title="{_T string="Previous"|escape}"
        >
            <i class="step backward icon"></i>
            <span class="sr-only">{_T string="Previous"}</span>
        </a>
        <div class="ui middle aligned disabled button">{$navigate.pos} / {$navigate.count}</div>
        <a
                href="{if isset($navigate.next)}{path_for name="member" data=["id" => $navigate.next]}{else}#{/if}"
                class="ui right icon button tooltip{if !isset($navigate.next)} disabled{/if}"
                title="{_T string="Next"|escape}"
        >
            <span class="sr-only">{_T string="Next"}</span>
            <i class="step forward icon"></i>
        </a>
    </nav>
{/if}