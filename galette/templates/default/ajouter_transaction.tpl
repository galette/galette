		<H1 class="titre">{_T("New transaction")}</H1>
		<FORM action="ajouter_transaction.php" method="post">
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
		<BLOCKQUOTE>
		<DIV align="center">
			<TABLE border="0" id="input-table">
				<TR>
					<TH {if $required.trans_desc eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Description:")}</TH>
					<TD colspan="3"><INPUT type="text" name="trans_desc" value="{$data.trans_desc}" maxlength="30" size="30"></TD>
				</TR>
				<TR>
					<TH {if $required.trans_date eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Date:")}</TH>
					<TD>
						<INPUT type="text" name="trans_date" value="{$data.trans_date}" maxlength="10"><BR>
						<DIV class="exemple">{_T("(dd/mm/yyyy format)")}</DIV>
					</TD>
					<TH {if $required.trans_amount eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Amount:")}</TH>
					<TD><INPUT type="text" name="trans_amount" value="{$data.trans_amount}" maxlength="10"></TD>
				</TR>
				<TR>
					<TH {if $required.id_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Originator:")}</TH>
					<TD colspan="3">
						<SELECT name="id_adh">
							{if $data.id_adh == 0}
							<OPTION>{_T("-- select a name --")}</OPTION>
							{/if}
							{html_options options=$adh_options selected=$data.id_adh}
						</SELECT>
					</TD>
				</TR>
{include file="display_dynamic_fields.tpl" is_form=true}
				<TR>
					<TH align="center" colspan="4"><BR><input type="submit" value="{_T("Save")}"></TH>
				</TR>
			</TABLE>
		</DIV>
		<BR>
		{_T("NB : The mandatory fields are in")} <font style="color: #FF0000">{_T("red")}</font>.
		</BLOCKQUOTE>
		<INPUT type="hidden" name="trans_id" value="{$data.trans_id}">
		<INPUT type="hidden" name="valid" value="1">
		</FORM>
