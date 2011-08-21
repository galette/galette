		<h1 id="titre">{_T string="Mailing"}</h1>
{if $pref_mail_method == constant('Mailing::METHOD_DISABLED')}
		<div id="errorbox">
			<h1>{_T string="- ERROR -"}</h1>
			<p>{_T string="Email sent is disabled in the preferences. Ask galette admin"}</p>
		</div>
{else}
		<form action="mailing_adherents.php#mail_preview" id="listform" method="post">
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
{if $warning_detected|@count != 0}
		<div id="warningbox">
			<h1>{_T string="- WARNING -"}</h1>
			<ul>
				{foreach from=$warning_detected item=warning}
					<li>{$warning}</li>
				{/foreach}
			</ul>
		</div>
{/if}

{assign var='count' value=$mailing->recipients|@count}
{if $count > 0}
		<p>
	{if $mailing->current_step == constant('Mailing::STEP_SEND')}
		{_T string="Your e-mail was sent to"}
	{else}
		{_T string="You are about to send an e-mail to"}
	{/if}
		{$count} {if $count != 1}{_T string="members"}{else}{_T string="member"}{/if}<br/>
	{if $mailing->current_step == constant('Mailing::STEP_START')}
		{_T string="Please compose your mail."}
	{/if}
		</p>
		<div>
	{if $mailing->current_step lt constant('Mailing::STEP_SEND')}
			<p>
				<label for="mailing_objet" class="bline">{_T string="Object:"}</label>
				<input type="text" name="mailing_objet" id="mailing_objet" value="{$mailing->subject}" size="80"/>
			</p>
			<p>
				<span class="fright"><a href="javascript:toggleMailingEditor('mailing_corps');" id="toggle_editor">{_T string="(De)Activate HTML editor"}</a></span>
				<label for="mailing_corps" class="bline">{_T string="Message:"}</label>
				<textarea name="mailing_corps" id="mailing_corps" cols="80" rows="15">{$mailing->message|escape}</textarea>
				<input type="hidden" name="html_editor_active" id="html_editor_active" value="{if $html_editor_active}1{else}0{/if}"/>
			</p>
			<p class="center">
				<input type="checkbox" name="mailing_html" id="mailing_html" value="1" {if $mailing->html eq 1 or $pref_editor_enabled eq 1}checked="checked"{/if}/><label for="mailing_html">{_T string="Interpret HTML"}</label><br/>
				<input type="submit" name="mailing_go" value="{_T string="Preview"}"/>
                <input type="submit" name="mailing_confirm" value="{_T string="Send"}"/>
            </p>
	{/if}
	{if $mailing->current_step > constant('Mailing::STEP_START') && $mailing->current_step lt constant('Mailing::STEP_SEND')}
			<div id="mail_preview">
				<p>{_T string="Message preview:"}</p>
				<p><span class="bline">{_T string="Object:"}</span>{$mailing->subject}</p>
				<p>
					<span class="bline">{_T string="Message:"}</span><br/>
		{if $mailing->html}
					{$mailing->message}
		{else}
					<pre>{$mailing->message}</pre>
		{/if}
				</p>
			</div>
			<p><input type="submit" name="mailing_confirm" value="{_T string="Send"}"/></p>
	{/if}

		</div>
{else}
		<strong>{_T string="None of the selected members has an email address."}</strong>
{/if}
		</form>

{assign var='count_unreachables' value=$mailing->unreachables|@count}
{if $count_unreachables > 0}
		<p>
		<strong>{$count_unreachables} {if $count_unreachables != 1}{_T string="unreachable members:"}{else}{_T string="unreachable member"}{/if}</strong><br/>
		{_T string="Some members you have selected have no e-mail address. However, you can generate envelope labels to contact them by snail mail."}
		</p>
		<div class="button-container">
            <a id="btnlabels" class="button" href="etiquettes_adherents.php">{_T string="Generate labels"}</a>
		</div>
{/if}
{/if}