		<h1 id="titre">{_T string="CVS database Export"}</h1>
		<p>{_T string="Each selected export will be stored into a separate file in the exports directory."}</p>
		<form class="form" action="export.php" method="post" enctype="multipart/form-data" name="form">
			<div class="bigtable">
{if $written|@count gt 0}
			<p>{_T string="The following files have benn written on disk:"}</p>
			<ul>
{foreach item=file from=$written}
				<li><a href="{$file}">{$file}</a></li>
{/foreach}
			</ul>
{/if}
{if $parameted|@count gt 0}
				<p>{_T string="Which parameted export(s) do you want to run?"}</p>
				<table>
{foreach item=param from=$parameted}
					<tr>
						<th>
							<label for="{$param.id}">{$param.name}</label>
						</th>
						<td>
							<input type="checkbox" name="export_parameted[]" id="{$param.id}" value="{$param.id}"/>
						</td>
					</tr>
{/foreach}
				</table>
{else}
				<p>{_T string="No parameted exports are available."}</p>
{/if}
				<p>{_T string="Additionnaly, which table(s) do you want to export?"}</p>
				<table>
{foreach item=table from=$tables_list}
					<tr>
						<th>
							<label for="{$table}">{$table}</label>
						</th>
						<td>
							<input type="checkbox" name="export_tables[]" id="{$table}" value="{$table}"/>
						</td>
					</tr>
{/foreach}
				</table>
{if $show_fields eq 'true'}
				<table id="fields_list">
				</table>
{/if}
			</div>
			<div class="button-container">
				<input type="submit" class="submit" name="valid" value="{_T string="Continue"}"/>
			</div>
		</form>