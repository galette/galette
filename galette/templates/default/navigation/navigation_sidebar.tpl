    <div class="ui simple left vertical accordion menu sidebar">
{if !$login->isLogged()}
    {if $cur_route neq "login"}
        <a href="{path_for name="slash"}"
           title="{_T string="Go back to Galette homepage"}"
           class="{if $cur_route eq "login"}active {/if}item"
        >
            <i class="icon home" aria-hidden="true"></i>
            {_T string="Home"}
        </a>
    {/if}

        {include
            file="navigation/public_pages.tpl"
            tips_position="right center"
            sign_in=true
        }

        {include
            file="ui_elements/languages.tpl"
            ui="item"
        }
{else}
        <div class="item">
            {include file="ui_elements/modes.tpl"}
        </div>

        {include file="navigation/navigation_items.tpl"}

        {include
            file="ui_elements/logout.tpl"
            ui="item"
        }
{/if}
    </div>
