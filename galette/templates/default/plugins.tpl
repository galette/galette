    <table class="listing">
        <!--<caption>{_T string="Active plugins"}</caption>-->
        <thead>
            <tr>
                <th class="listing">{_T string="Name"}</th>
                <th class="listing">{_T string="Description"}</th>
                <th class="listing">{_T string="Author"}</th>
                <th class="listing">{_T string="Version"}</th>
                <th class="listing row_actions"></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th colspan="5" class="center"><strong>{_T string="Active plugins"}</strong></th>
            </tr>
{foreach from=$plugins_list key=name item=plugin}
            <tr>
                <td>{$plugin.name} ({$name})</td>
                <td>{$plugin.desc}</td>
                <td>{$plugin.author}</td>
                <td>{$plugin.version}</td>
                <td>
                    <a href="?deactivate={$name}" title="{_T string="Click here to deactivate plugin '%name'" pattern="/%name/" replace=$plugin.name}">
                        <img src="{$template_subdir}images/icon-on.png" alt="{_T string="Disable plugin"}"/>
                    </a>
                </td>
            </tr>
{foreachelse}
            <tr>
                <td colspan="4">{_T string="No active plugin."}</td>
            </tr>
{/foreach}
            <tr>
                <th colspan="5" class="center"><strong>{_T string="Inactive plugins"}</strong></th>
            </tr>
{foreach from=$plugins_disabled_list key=name item=plugin}
            <tr>
                <td colspan="4">{$name}</td>
                <td>
                    <a href="?activate={$name}" title="{_T string="Click here to activate plugin '%name'" pattern="/%name/" replace=$name}">
                        <img src="{$template_subdir}images/icon-off.png" alt="{_T string="Enable plugin"}"/>
                    </a>
                </td>
            </tr>
{foreachelse}
            <tr>
                <td colspan="4">{_T string="No active plugin."}</td>
            </tr>
{/foreach}
        </tbody>
    </table>