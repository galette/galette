{extends file="page.tpl"}
{block name="content"}
    <h2>
        {$name} <tt>{$version} - {$date}</tt>
        <br/><span class="author">
            {_T string="By %name" pattern="/%name/" replace=$author}
        </span>
    </h2>
    {if isset($module)}
    <dl>
        <dt><strong>{_T string="Name:"}</strong></dt>
        <dd>{$module.name}</dd>
        <dt><strong>{_T string="Description:"}</strong></dt>
        <dd>{$module.desc}</dd>
        <dt><strong>{_T string="Version:"}</strong></dt>
        <dd>{$module.version}</dd>
        <dt><strong>{_T string="Date:"}</strong></dt>
        <dd>{$module.date}</dd>
        <dt><strong>{_T string="Author:"}</strong></dt>
        <dd>{$module.author}</dd>
        <dt><strong>{_T string="Path:"}</strong></dt>
        <dd>{$module.root}</dd>
        <dt><strong>{_T string="Main route:"}</strong></dt>
        <dd>{$module.route}</dd>
        <dt><strong>{_T string="ACLs"}</strong>
        <dd>
            <table>
                <thead>
                    <th>{_T string="Route"}</th>
                    <th>{_T string="ACL"}</th>
                </thead>
       {foreach item=acl key=route from=$module.acls}
                <tr>
                    <td><tt>{$route}</tt></td>
                    <td><tt>{$acl}</tt></td>
                </tr>
        {foreachelse}
                <td colspan="2"><strong>{_T string="No ACLs!"}</strong></td>
        {/foreach}
            </table>
        </dd>
    </dl>

    <h3>{_T string="Raw informations"}</h3>
    <pre>{$module|print_r}</pre>
    {/if}
{/block}
