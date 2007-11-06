		<h1 id="titre">{_T("Utilities")}</h1>
{if $error_detected|@count != 0}
		<div id="errorbox">
			<h1>{_T("- ERROR -")}</h1>
			<ul>
{foreach from=$error_detected item=error}
				<li>{$error}</li>
{/foreach}
			</ul>
		</div>
{/if}
{if $warning_detected|@count != 0}
		<div id="warningbox">
			<h1>{_T("- WARNING -")}</h1>
			<ul>
{foreach from=$warning_detected item=warning}
				<li>{$warning}</li>
{/foreach}
			</ul>
		</div>
{/if}
<form action="utilitaires.php" method="post" enctype="multipart/form-data"> 
	<table width="100%" id="input-table"> 
		<tr>
			<th class="listing left">{_T("Action")}</th>
			<th class="listing" width="100%">{_T("Parameters")}</th>
		</tr>
		<tr>
			<td class="listing left">
				<input type="submit" class="submit" name="xmlupload" value="{_T("Load Document Model")}"/>
			</td>
			<td class="listing left">
				<input type="file" name="loadxml" id="xmlfile" value="{$loadxml}" maxlength="64" size="64"/>
			</td>
		</tr>
		<tr>
			<td class="listing left">
				<input type="submit" class="submit" name="fieldsfile" value="{_T("Generate Field List for Document Editor")}"/>
			</td>
			<td class="listing left">
				<input type="text" name="exportfields" value="{$exportfields}" maxlength="64" size="64"/>
			</td>
		</tr>
	</table>
</form>
			