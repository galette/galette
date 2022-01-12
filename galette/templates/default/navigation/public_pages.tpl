{if $preferences->showPublicPages($login) eq true}
    <a href="{path_for name="publicList" data=["type" => "list"]}"
        class="{if $cur_route eq "publicList" and $cur_subroute eq "list"}active {/if}item"
        title="{_T string="Members list"}"
        {if isset($tips_position)}data-position="{$tips_position}"{/if}
    >
        <i class="icon address book" aria-hidden="true"></i>
        {_T string="Members list"}
    </a>
    <a href="{path_for name="publicList" data=["type" => "trombi"]}"
        class="{if $cur_route eq "publicList" and $cur_subroute eq "trombi"}active {/if}item"
        title="{_T string="Trombinoscope"}"
        {if isset($tips_position)}data-position="{$tips_position}"{/if}
    >
        <i class="icon user friends" aria-hidden="true"></i>
        {_T string="Trombinoscope"}
    </a>
{/if}

    {* Include plugins menu entries *}
    {$plugins->getPublicMenus($tpl, true)}

{if isset($sign_in) and $sign_in eq true}
    <div class="{if isset($sign_in_side)}right {/if}item">
    {if $preferences->pref_bool_selfsubscribe eq true and $cur_route neq "subscribe"}
        <a
            href="{path_for name="subscribe"}"
            class="ui basic button"
            title="{_T string="Subscribe"}"
            data-position="bottom center"
        >
            <i class="icon add user" aria-hidden="true"></i>
            {_T string="Subscribe"}
        </a>
    {/if}
    {if $cur_route neq "login"}
        <a
            href="{path_for name="slash"}"
            class="ui primary button"
            title="{_T string="Login"}"
            data-position="bottom center"
        >
            <i class="icon sign in alt" aria-hidden="true"></i>
            {_T string="Login"}
        </a>
    {/if}
    </div>
{/if}
