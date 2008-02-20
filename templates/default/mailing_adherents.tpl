		<h1 class="titre">{_T("Mailing")}</h1>
		<!--
		attribute name for form element is forbiden in xhtml strict
		<form action="mailing_adherents.php" method="post" name="listform">
		//-->
		<form action="mailing_adherents.php#mail_preview" method="post">
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
			<p>
				<label for="mailing_object" class="bline">{_T("Object:")}</label>
				<input type="text" name="mailing_objet" id="mailing_objet" value="{$data.mailing_objet}" size="80"/>
			</p>
			<p>
				<label for="mailing_corps" class="bline">{_T("Message:")}</label>
				<textarea name="mailing_corps" id="mailing_corps" cols="80" rows="15">{$data.mailing_corps}</textarea>
			</p>
			<p class="center">
				<input type="checkbox" name="mailing_html" id="mailing_html" value="1" {if $data.mailing_html eq 1}checked="checked"{/if}/><label for="mailing_html">{_T("Interpret HTML")}</label><br/>
				<input type="submit" class="submit" name="mailing_go" value="{_T("Preview")}"/>
			</p>
	{if $etape>0}
			<div id="mail_preview">
				<p>{_T("Message preview:")}</p>
				<p><span class="bline">{_T("Object:")}</span>{$data.mailing_objet}</p>
				<p>
					<span class="bline">{_T("Message:")}</span><br/>
		{if $data.mailing_html eq 1}
					{$data.mailing_corps}
		{else}
					<pre>{$data.mailing_corps_display}</pre>
		{/if}
				</p>
			</div>
	{/if}
			<p><input type="submit" class="submit" name="mailing_confirm" value="{_T("Send")}"/></p>

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
