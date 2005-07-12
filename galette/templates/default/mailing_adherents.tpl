		<H1 class="titre">{_T("Mailing")}</H1>
		<FORM action="mailing_adherents.php" method="post" name="listform">
{if $error_detected|@count != 0}
		<DIV id="errorbox">
			<H1>{_T("- ERROR -")}</H1>
			<UL>
	{foreach from=$error_detected item=error}
				<LI>{$error}<LI>
	{/foreach}
			</UL>
		</DIV>
{/if}
		<P>
{if $nb_reachable_members > 0}
	{if $etape==2}
		{_T("Your e-mail was sent to")}
	{else}
		{_T("You are about to send an e-mail to")}
	{/if}
		{$nb_reachable_members} {if $nb_reachable_members != 1}{_T("members")}{else}{_T("member")}{/if}<br/>
	{if $etape==0}
		{_T("Please compose your mail.")}
	{/if}
		</P>
		<DIV align="center">
		<TABLE border="0" id="input-table">
			<TR>
				<TH class="libelle">{_T("Object:")}</TH>
			</TR>
			<TR>
				<TD>
	{if $etape==0}
				<INPUT type="text" name="mailing_objet" value="{$data.mailing_objet}" size="80"/>
	{else}
				<PRE>{$data.mailing_objet}</PRE>
				<INPUT type="hidden" name="mailing_objet" value="{$data.mailing_objet}"/>
	{/if}
				</TD>
			</TR>
			<TR>
				<TH class="libelle">{_T("Message:")}</TH>
			</TR>
			<TR>
	{if $etape==0}				
				<TD>
				<TEXTAREA name="mailing_corps" cols="80" rows="15">{$data.mailing_corps}</TEXTAREA>
				</TD>
	{else}
				<TD class="mail_preview">
		{if $data.mailing_html eq 1}
				{$data.mailing_corps}
				<PRE>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</PRE>
		{else}
				<PRE>{$data.mailing_corps_display}</PRE>
				<PRE>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</PRE>
		{/if}
				<INPUT type="hidden" name="mailing_corps" value="{$data.mailing_corps_display}"/>
				</TD>
	{/if}
			</TR>
			<TR>
				<TD align="center">
	{if $etape==0}
				<INPUT type="checkbox" name="mailing_html" value="1" {if $data.mailing_html eq 1}checked="checked"{/if}/>{_T("Interpret HTML")}<br/><br/>
				<INPUT type="submit" name="mailing_go" value="{_T("Preview")}"/>
	{elseif $etape==1}
				{_T("HTML interpretation:")} {if $data.mailing_html eq 1}{_T("ON")}{else}{_T("OFF")}{/if}<br/><br/>
				<INPUT type="hidden" name="mailing_html" value="{if $data.mailing_html eq 1}1{else}0{/if}"/>
				<INPUT type="submit" name="mailing_reset" value="{_T("Reedit")}"/>
				<INPUT type="submit" name="mailing_confirm" value="{_T("Send")}"/>
	{else}
				<INPUT type="submit" name="mailing_done" value="{_T("Go back to the member listing")}"/>
	{/if}
				</TD>
			</TR>
		</TABLE>
		</DIV>
{else}
		<B>{_T("None of the selected members has an email address.")}</B>
{/if}
		</FORM>
		{if $nb_unreachable_members > 0}
		<P>
		<B>{$nb_unreachable_members} {if $nb_unreachable_members != 1}{_T("unreachable members:")}{else}{_T("unreachable member")}{/if}</B><br/>
		{_T("Some members you have selected have no e-mail address. However, you can generate envelope labels to contact them by snail mail.")}
		</P>
		<FORM method="get" action="etiquettes_adherents.php">
			<INPUT type="submit" value="{_T("Generate labels")}"/>
		</FORM>
		{/if}
