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
        <div class="mailing">
            <section class="mailing_infos">
                <header class="ui-state-default ui-state-active">{_T string="Mailing informations"}</header>
                <p>{_T string="You are about to send an e-mail to <strong>%s members</strong>" pattern="/%s/" replace=$count}</p>
        {assign var='count_unreachables' value=$mailing->unreachables|@count}
        {if $count_unreachables > 0}
                <p>
                    <strong>{$count_unreachables} {if $count_unreachables != 1}{_T string="unreachable members:"}{else}{_T string="unreachable member"}{/if}</strong><br/>
                    {_T string="Some members you have selected have no e-mail address. However, you can generate envelope labels to contact them by snail mail."}
                    <br/><a id="btnlabels" class="button" href="etiquettes_adherents.php">{_T string="Generate labels"}</a>
                </p>
        {/if}
            </section>
        {if $mailing->current_step eq constant('Mailing::STEP_START')}
            <section class="mailing_write">
                <header class="ui-state-default ui-state-active">{_T string="Write your mailing"}</header>
                <div>
                    <label for="mailing_objet" class="bline">{_T string="Object:"}</label>
                    <input type="text" name="mailing_objet" id="mailing_objet" value="{$mailing->subject}" size="80" required/>
                </div>
                <div>
                    <span class="fright"><a href="javascript:toggleMailingEditor('mailing_corps');" id="toggle_editor">{_T string="(De)Activate HTML editor"}</a></span>
                    <label for="mailing_corps" class="bline">{_T string="Message:"}</label>
                    <textarea name="mailing_corps" id="mailing_corps" cols="80" rows="15" required>{$mailing->message|escape}</textarea>
                    <input type="hidden" name="html_editor_active" id="html_editor_active" value="{if $html_editor_active}1{else}0{/if}"/>
                </div>
                <div class="center">
                    <input type="checkbox" name="mailing_html" id="mailing_html" value="1" {if $mailing->html eq 1 or $pref_editor_enabled eq 1}checked="checked"{/if}/><label for="mailing_html">{_T string="Interpret HTML"}</label><br/>
                    <input type="submit" id="btnpreview" name="mailing_go" value="{_T string="Preview"}"/>
                    <input type="submit" id="btnsave" name="mailing_save" value="{_T string="Save"}"/>
                    <input type="submit" id="btnsend" name="mailing_confirm" value="{_T string="Send"}"/>
                </div>
            </section>
        {/if}
        {if $mailing->current_step eq constant('Mailing::STEP_PREVIEW')}
            <section class="mailing_write" id="mail_preview">
                <header class="ui-state-default ui-state-active">{_T string="Preview your mailing"}</header>
                <div>
                    <p><span class="bline">{_T string="Object:"}</span>{$mailing->subject}</p>
                    <p>
                        <span class="bline">{_T string="Message:"}</span><br/>
            {if $mailing->html}
					{$mailing->message}
            {else}
    					<pre>{$mailing->message}</pre>
            {/if}
        			</p>
            		<p><input type="button" class="button" id="btnback" onclick="javascript:back()" value="{_T string="Modifiy mailing"}"/><input type="submit" name="mailing_confirm" value="{_T string="Send"}"/></p>
                </div>
        {/if}

            </section>
    {else}
            <strong>{_T string="None of the selected members has an email address."}</strong>
    {/if}
        </div>
		</form>
</section>
{/if}