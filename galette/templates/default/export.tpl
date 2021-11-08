{extends file="page.tpl"}

{block name="content"}
        <form class="form" action="{path_for name="doExport"}" method="post" enctype="multipart/form-data">
        <p>{_T string="Each selected export will be stored into a separate file in the exports directory."}</p>

{assign var="written_exports" value=$flash->getMessage('written_exports')}
{if is_array($written_exports) && $written_exports|@count > 0}
        <div id="successbox">
            <p>{_T string="The following files have been written on disk:"}</p>
            <ul>
    {foreach from=$flash->getMessage('written_exports') item=ex}
                <li>{$ex}</li>
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
                                <th class="id_row">#</th>
                                <th>{_T string="Name"}</th>
                                <th>{_T string="Date"}</th>
                                <th>{_T string="Size"}</th>
                                <th class="actions_row"></th>
                            </tr>
                        </thead>
                        <tbody>
    {foreach item=export from=$existing name=existing_list name=eachexport}
                            <tr class="{if $smarty.foreach.existing_list.iteration % 2 eq 0}even{else}odd{/if}">
                                <td data-scope="id">
                                    {$smarty.foreach.eachexport.iteration}
                                </td>
                                <td data-scope="row">
                                    <a href="{path_for name="getCsv" data=["type" => "export", "file" => $export.name]}">{$export.name}</a>
                                </td>
                                <td data-title="{_T string="Date"}">
                                    {$export.date}
                                </td>
                                <td data-title="{_T string="Size"}">
                                    {$export.size}
                                </td>
                                <td class="actions_row">
                                    <a
                                        href="{path_for name="removeCsv" data=["type" => "export", "file" => $export.name]}"
                                        class="delete tooltip"
                                    >
                                        <i class="fas fa-trash"></i>
                                        <span class="sr-only">{_T string="Remove '%file' from disk" pattern="/%file/" replace=$export.name}</span>
                                    </a>
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
                                <th class="small_head"/>
                                <th>{_T string="Name"}</th>
                                <th>{_T string="Description"}</th>
                            </tr>
                        </thead>
                        <tbody>
    {foreach item=param from=$parameted name=parameted_list}
                            <tr class="{if $smarty.foreach.parameted_list.iteration % 2 eq 0}even{else}odd{/if}">
                                <td data-scope="id">
                                    <input type="checkbox" name="export_parameted[]" id="{$param.id}" value="{$param.id}"/>
                                </td>
                                <td data-scope="row">
                                    <label for="{$param.id}">{$param.name}</label>
                                </td>
                                <td data-title="{_T string="Description"}">
                                    <label for="{$param.id}">{$param.description}</label>
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
                    <table class="listing same">
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
                {include file="forms_types/csrf.tpl"}
            </div>
        </form>
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            $(function() {
                _collapsibleFieldsets();
                {include file="js_removal.tpl"}
            });
        </script>
{/block}
