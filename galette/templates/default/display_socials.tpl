{if $socials|@count > 0}
<div class="ui basic fitted segment">
    <div class="ui styled fluid accordion row">
        <div class="active title">
            <i class="icon dropdown"></i>
            {_T string="Social networks"}
        </div>
        <div class="active content field">
            <table class="ui very basic striped collapsing stackable padded table">
    {foreach item=social from=$socials}
            <tr>
                <th>{$social->getSystemType($social->type)}</th>
                <td>{$social->displayUrl()}</td>
            </tr>
    {/foreach}
        </table>
        </div>
    </div>
</div>
{/if}
