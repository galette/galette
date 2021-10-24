{if $socials|@count > 0}
        <table class="details" id="social">
            <caption class="ui-state-active ui-corner-top">{_T string="Social networks"}</caption>
    {foreach item=social from=$socials}
            <tr>
                <th>{$social->getSystemType($social->type)}</th>
                <td>{$social->displayUrl()}</td>
            </tr>
    {/foreach}
        </table>
{/if}
