		<h1 class="titre">{$form_title}</h1>
		<form action="editer_intitules.php" method="post"> 						
{if $error_detected|@count != 0}
		<div id="errorbox">
			<h1>{_T string="- ERROR -"}</h1>
			<ul>
{foreach from=$error_detected item=error}
				<li>{$error}</li>
{/foreach}
			</ul>
		</div>
{/if}
		<blockquote>
		<div align="center">
			<table border="0" id="input-table"> 
				<tr> 
					<th id="libelle">{_T string="Name:"}</th> 
					<td colspan="2">
{if $table == 'statuts'}
						<input type="hidden" name="mod" value="{$entry.id_statut}"/>
						<input type="text" name="libelle_statut" value="{$entry.libelle_statut} "/>
{elseif $table == 'types_cotisation'}
						<input type="hidden" name="mod" value="{$entry.id_type_cotis} "/>
						<input type="text" name="libelle_type_cotis" value="{$entry.libelle_type_cotis}"/>
{/if}
					</td> 
				</tr>
				<tr>
{if $table == 'statuts'}
					<th id="libelle">{_T string="Priority:"}</th> 
{elseif $table == 'types_cotisation'}
					<th id="libelle">{_T string="Extends membership?"}</th> 
{/if}
					<td>
{if $table == 'statuts'}
						<input type="text" size="4" name="priorite_statut" value="{$entry.priorite_statut}" />
{elseif $table == 'types_cotisation'}
						<input type="checkbox" name="cotis_extension" value="1" {if $entry.cotis_extension == 1}checked="checked" {/if} />
{/if}
					</td>
				</tr>
				<tr> 
					<th align="center" colspan="2"><br/><input type="submit" class="submit" name="valid" value="{_T string="Save"}"/></th> 
					<th align="center" colspan="2"><br/><input type="submit" class="submit" name="cancel" value="{_T string="Cancel"}"/></th> 
				</tr> 
			</table>
		</div>
		<br/> 
		</blockquote> 
		<input type="hidden" name="table" value="{$table}" />
		</form>
