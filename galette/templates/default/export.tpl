		<h1 id="titre">{_T string="CVS database Export"}</h1>
		<p>{_T string="Each selected export will be stored into a separate file in the exports directory."}</p>
		<form class="form" action="export.php" method="post" enctype="multipart/form-data">
{if $written|@count gt 0}
			<p>{_T string="The following files have been written on disk:"}</p>
			<ul>
{foreach item=file from=$written}
				<li><a href="{$file}">{$file}</a></li>
{/foreach}
			</ul>
{/if}
{if $parameted|@count gt 0}
			<p>{_T string="Which parameted export(s) do you want to run?"}</p>
			<table id="listing">
				<thead>
					<tr>
						<th class="listing">{_T string="Name"}</th>
						<th class="listing">{_T string="Description"}</th>
						<th class="listing small_head"/>
					</tr>
				</thead>
{foreach item=param from=$parameted}
				<tr>
					<td class="cotis-normal">
						<label for="{$param.id}">{$param.name}</label>
					</td>
					<td class="cotis-normal">
						<label for="{$param.id}">{$param.description}</label>
					</td>
					<td class="cotis-normal">
						<input type="checkbox" name="export_parameted[]" id="{$param.id}" value="{$param.id}"/>
					</td>
				</tr>
{/foreach}
			</table>
{else}
			<p>{_T string="No parameted exports are available."}</p>
{/if}
			<p>{_T string="Additionnaly, which table(s) do you want to export?"}</p>
			<table id="tables_list">
				<thead>
					<tr>
						<th class="listing">{_T string="Table name"}</th>
						<th class="listing small_head"/>
					</tr>
				</thead>
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
			</table>
{if $show_fields eq 'true'}
			<table id="fields_list">
			</table>
{/if}
			<div class="button-container">
				<input type="submit" class="submit" name="valid" value="{_T string="Continue"}"/>
			</div>
		</form>