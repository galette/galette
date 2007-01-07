		<h1 class="titre">{_T("Mailing")}</h1>
		<!--
		attribute name for form element is forbiden in xhtml strict
		<form action="mailing_adherents.php" method="post" name="listform">
		//-->
		<form action="mailing_adherents.php" method="post">
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

{if $nb_reachable_members > 0}
		<p>
	{if $etape==2}
		{_T("Your e-mail was sent to")}
	{else}
		{_T("You are about to send an e-mail to")}
	{/if}
		{$nb_reachable_members} {if $nb_reachable_members != 1}{_T("members")}{else}{_T("member")}{/if}<br/>
	{if $etape==0}
		{_T("Please compose your mail.")}
	{/if}
		</p>
		<div>
		<table border="0" id="input-table">
			<tr>
				<th class="libelle">{_T("Object:")}</th>
			</tr>
			<tr>
				<td>
	{if $etape==0}
				<input type="text" name="mailing_objet" value="{$data.mailing_objet}" size="80"/>
	{else}
				<pre>{$data.mailing_objet}</pre>
				<input type="hidden" name="mailing_objet" value="{$data.mailing_objet}"/>
	{/if}
				</td>
			</tr>
			<tr>
				<th class="libelle">{_T("Message:")}</th>
			</tr>
			<tr>
	{if $etape==0}				
				<td>
				<textarea name="mailing_corps" cols="80" rows="15">{$data.mailing_corps}</textarea>
				</td>
	{else}
				<td class="mail_preview">
		{if $data.mailing_html eq 1}
				{$data.mailing_corps}
				<pre>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</pre>
		{else}
				<pre>{$data.mailing_corps_display}</pre>
				<pre>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</pre>
		{/if}
				<input type="hidden" name="mailing_corps" value="{$data.mailing_corps_display}"/>
				</td>
	{/if}
			</tr>
			<tr>
				<td style="text-align:center">
	{if $etape==0}
				<input type="checkbox" name="mailing_html" value="1" {if $data.mailing_html eq 1}checked="checked"{/if}/>{_T("Interpret HTML")}<br/><br/>
				<input type="submit" class="submit" name="mailing_go" value="{_T("Preview")}"/>
	{elseif $etape==1}
				{_T("HTML interpretation:")} {if $data.mailing_html eq 1}{_T("ON")}{else}{_T("OFF")}{/if}<br/><br/>
				<input type="hidden" name="mailing_html" value="{if $data.mailing_html eq 1}1{else}0{/if}"/>
				<input type="submit" class="submit" name="mailing_reset" value="{_T("Reedit")}"/>
				<input type="submit" class="submit" name="mailing_confirm" value="{_T("Send")}"/>
	{else}
				<input type="submit" class="submit" name="mailing_done" value="{_T("Go back to the member listing")}"/>
	{/if}
				</td>
			</tr>
		</table>
		</div>
{else}
		<strong>{_T("None of the selected members has an email address.")}</strong>
{/if}
		</form>
		{if $nb_unreachable_members > 0}
		<p>
		<strong>{$nb_unreachable_members} {if $nb_unreachable_members != 1}{_T("unreachable members:")}{else}{_T("unreachable member")}{/if}</strong><br/>
		{_T("Some members you have selected have no e-mail address. However, you can generate envelope labels to contact them by snail mail.")}
		</p>
		<div class="button-container">
			<div class="button-link button-labels">
				<a href="etiquettes_adherents.php">{_T("Generate labels")}</a>
			</div>
		</div>
		{/if}
