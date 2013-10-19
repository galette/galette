        <form class="form" action="export.php" method="post" enctype="multipart/form-data">
        <p>{_T string="Each selected export will be stored into a separate file in the exports directory."}</p>
{if $written|@count gt 0}
        <div id="successbox">
            <p>{_T string="The following files have been written on disk:"}</p>
            <ul>
{foreach item=ex from=$written}
                <li><a href="get_export.php?file={$ex.name}">{$ex.name} ({$ex.file})</a></li>
{/foreach}
            </ul>
        </div>
{/if}
{if $existing|@count gt 0}
            <fieldset>
                <legend class="ui-state-active ui-corner-top">{_T string="Existing exports"}</legend>
                <div>
                    <p>{_T string="The following exports already seems to exist on the disk:"}</p>
                    <table class="listing">
                        <thead>
                            <tr>
                                <th>{_T string="Name"}</th>
                                <th>{_T string="Date"}</th>
                                <th>{_T string="Size"}</th>
                                <th class="actions_row"></th>
                            </tr>
                        </thead>
                        <tbody>
    {foreach item=export from=$existing name=existing_list}
                            <tr class="{if $smarty.foreach.existing_list.iteration % 2 eq 0}even{else}odd{/if}">
                                <td >
                                    <a href="get_export.php?file={$export.name}">{$export.name}</a>
                                </td>
                                <td>
                                    {$export.date}
                                </td>
                                <td>
                                    {$export.size}
                                </td>
                                <td class="actions_row">
                                    <a href="export.php?sup={$export.name}" title="{_T string="Remove '%file' from disk" pattern="/%file/" replace=$export.name}"><img src="{$template_subdir}images/delete.png" alt="{_T string="Delete"}"/></a>
                                </td>
                            </tr>
    {/foreach}
                        </tbody>
                    </table>
                </div>
            </fieldset>
{/if}
            <fieldset>
                <legend class="ui-state-active ui-corner-top">{_T string="Parameted exports"}</legend>
                <div>
{if $parameted|@count gt 0}
                    <p>{_T string="Which parameted export(s) do you want to run?"}</p>
                    <table class="listing">
                        <thead>
                            <tr>
                                <th>{_T string="Name"}</th>
                                <th>{_T string="Description"}</th>
                                <th class="small_head"/>
                            </tr>
                        </thead>
                        <tbody>
    {foreach item=param from=$parameted name=parameted_list}
                            <tr class="{if $smarty.foreach.parameted_list.iteration % 2 eq 0}even{else}odd{/if}">
                                <td>
                                    <label for="{$param.id}">{$param.name}</label>
                                </td>
                                <td>
                                    <label for="{$param.id}">{$param.description}</label>
                                </td>
                                <td>
                                    <input type="checkbox" name="export_parameted[]" id="{$param.id}" value="{$param.id}"/>
                                </td>
                            </tr>
{/foreach}
                        </tbody>
                    </table>
{else}
                    <p>{_T string="No parameted exports are available."}</p>
{/if}
                </div>
            </fieldset>

            <fieldset>
                <legend class="ui-state-active ui-corner-top">{_T string="Galette tables exports"}</legend>
                <div>
                    <p>{_T string="Additionnaly, which table(s) do you want to export?"}</p>
                    <table class="listing">
                        <thead>
                            <tr>
                                <th>{_T string="Table name"}</th>
                                <th class="small_head"/>
                            </tr>
                        </thead>
                        <tbody>
{foreach item=table from=$tables_list name=tables_list}
                            <tr class="{if $smarty.foreach.tables_list.iteration % 2 eq 0}even{else}odd{/if}">
                                <td class="left">
                                    <label for="{$table}">{$table}</label>
                                </td>
                                <td>
                                    <input type="checkbox" name="export_tables[]" id="{$table}" value="{$table}"/>
                                </td>
                            </tr>
{/foreach}
                        </tbody>
                    </table>
                </fieldset>
            <div class="button-container">
                <input type="submit" name="valid" value="{_T string="Continue"}"/>
            </div>
        </form>

        <script type="text/javascript">
            $(function() {
                _collapsibleFieldsets();
            });
        </script>
