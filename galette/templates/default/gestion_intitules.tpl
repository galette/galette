<h1 id="titre">{$form_title}</h1>
<form action="gestion_intitules.php" method="post" enctype="multipart/form-data">
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

<ul id="tabs">
  {foreach from=$all_forms key=key item=form}
    <li{if $class eq $key} class="current_tab"{/if}>
      <a href="?class={$key}">{$form}</a>
    </li>
  {/foreach}
</ul>

<div class="tabbed">
  <table id="input-table">
    <thead>
      <tr>
	<th class="listing" class="id_row">#</th>
	<th class="listing">{_T string="Name"}</th>
	{if $class == 'ContributionsTypes'}
	  <th class="listing">{_T string="Extends membership?"}</th>
	{elseif $class == 'Status'}
	  <th class="listing">{_T string="Priority"}</th>
	{/if}
	<th class="listing">{_T string="Actions"}</th>
      </tr>
    </thead>
    <tfoot>
      <tr>
	<td class="listing">&nbsp;</td>
	<td class="listing left">
	  <input size="40" type="text" name="{$fields.$class.name}"/>
	</td>
	<td class="listing left">
	  {if $class == 'ContributionsTypes'}
	    <select name="{$fields.$class.field}">
	      <option value="0" selected="selected">{_T string="No"}</option>
	      <option value="1">{_T string="Yes"}</option>
	    </select>
	  {elseif $class == 'Status'}
	    <input size="4" type="text" name="{$fields.$class.field}" value="99" />
	  {/if}
	</td>
	<td class="listing center">
	  <input type="hidden" name="new" value="1" />
	  <input type="hidden" name="class" value="{$class}" />
	  <input type="submit" class="submit" name="valid" value="{_T string="Add"}"/>
	</td>
      </tr>
    </tfoot>
    <tbody>
      {foreach from=$entries item=entry}
        <tr>
	  <td class="listing">{$entry.id}</td>
	  <td class="listing left">{$entry.name|escape}</td>
	  <td class="listing">
	    {if $class == 'ContributionsTypes'}
	      {$entry.extends}
	    {elseif $class == 'Status'}
	      {$entry.priority}
	    {/if}
	  </td>
	  <td class="listing center actions_row">
	    <a href="gestion_intitules.php?class={$class}&amp;id={$entry.id}">
	      <img src="{$template_subdir}images/icon-edit.png" alt="{_T string="Edit '%s' field" pattern="/%s/" replace=$entry.name}" title="{_T string="Edit '%s' field" pattern="/%s/" replace=$entry.name}" width="16" height="16"/>
	    </a>
	    <a onclick="return confirm('{_T string="Do you really want to delete this category?"|escape:"javascript"}')" href="gestion_intitules.php?class={$class}&amp;del={$entry.id}">
	      <img src="{$template_subdir}images/icon-trash.png" alt="{_T string="Delete '%s' field" pattern="/%s/" replace=$entry.name}" title="{_T string="Delete '%s' field" pattern="/%s/" replace=$entry.name}" width="16" height="16" />
	    </a>
	  </td>
	</tr>
      {/foreach}
    </tbody>
  </table>
</div>
</form>