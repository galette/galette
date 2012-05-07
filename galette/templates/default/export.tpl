		<form class="form" action="export.php" method="post" enctype="multipart/form-data">
		<p>{_T string="Each selected export will be stored into a separate file in the exports directory."}</p>
{if $written|@count gt 0}
			<p>{_T string="The following files have been written on disk:"}</p>
			<ul>
{foreach item=ex from=$written}
				<li><a href="get_export.php?file={$ex.name}">{$ex.name} ({$ex.file})</a></li>
{/foreach}
			</ul>
{/if}
{if $existing|@count gt 0}
            <fieldset>
                <legend class="ui-state-active ui-corner-top">{_T string="Existing exports"}</legend>
                <div>
                    <p>{_T string="The following exports already seems to exist on the disk:"}</p>
                    <table class="listing">
                        <thead>
                            <tr>
                                <th class="listing">{_T string="Name"}</th>
                                <th class="listing">{_T string="Date"}</th>
                                <th class="listing">{_T string="Size"}</th>
                            </tr>
                        </thead>
                        <tbody>
    {foreach item=export from=$existing name=existing_list}
                            <tr>
                                <td class="tbl_line_{if $smarty.foreach.existing_list.iteration % 2 eq 0}even{else}odd{/if}">
                                    <a href="get_export.php?file={$export.name}">{$export.name}</a>
                                </td>
                                <td class="tbl_line_{if $smarty.foreach.existing_list.iteration % 2 eq 0}even{else}odd{/if}">
                                    {$export.date}
                                </td>
                                <td class="tbl_line_{if $smarty.foreach.existing_list.iteration % 2 eq 0}even{else}odd{/if}">
                                    {$export.size}
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
                                <th class="listing">{_T string="Name"}</th>
                                <th class="listing">{_T string="Description"}</th>
                                <th class="listing small_head"/>
                            </tr>
                        </thead>
                        <tbody>
    {foreach item=param from=$parameted name=parameted_list}
                            <tr>
                                <td class="tbl_line_{if $smarty.foreach.parameted_list.iteration % 2 eq 0}even{else}odd{/if}">
                                    <label for="{$param.id}">{$param.name}</label>
                                </td>
                                <td class="tbl_line_{if $smarty.foreach.parameted_list.iteration % 2 eq 0}even{else}odd{/if}">
                                    <label for="{$param.id}">{$param.description}</label>
                                </td>
                                <td class="tbl_line_{if $smarty.foreach.parameted_list.iteration % 2 eq 0}even{else}odd{/if}">
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
                    <table id="tables_list">
                        <thead>
                            <tr>
                                <th class="listing">{_T string="Table name"}</th>
                                <th class="listing small_head"/>
                            </tr>
                        </thead>
                        <tbody>
{foreach item=table from=$tables_list name=tables_list}
                            <tr>
                                <th class="tbl_line_{if $smarty.foreach.tables_list.iteration % 2 eq 0}even{else}odd{/if} left">
                                    <label for="{$table}">{$table}</label>
                                </th>
                                <td class="tbl_line_{if $smarty.foreach.tables_list.iteration % 2 eq 0}even{else}odd{/if}">
                                    <input type="checkbox" name="export_tables[]" id="{$table}" value="{$table}"/>
                                </td>
                            </tr>
{/foreach}
                        </tbody>
                    </table>
                </fieldset>
{if $show_fields eq 'true'}
			<table id="fields_list">
			</table>
{/if}
			<div class="button-container">
				<input type="submit" name="valid" value="{_T string="Continue"}"/>
			</div>
		</form>

		<script type="text/javascript">
            $(function() {
                _collapsibleFieldsets();
            });
		</script>
