		<H1 class="titre">{_T("Contribution card")} ({if $data.id_cotis != ""}{_T("modification")}{else}{_T("creation")}{/if})</H1>
		<FORM action="ajouter_contribution.php" method="post">
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
					<TH {if $required.id_adh eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Contributor:")}</TH>
					<TD>
						<SELECT name="id_adh">
							{if $adh_selected eq 0}
							<OPTION value="0">{_T("-- select a name --")}</OPTION>
							{/if}
							{html_options options=$adh_options selected=$data.id_adh}
						</SELECT>
					</TD>
					<TH {if $required.id_type_cotis eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Contribution type:")}</TH>
					<TD>
						<SELECT name="id_type_cotis"
							{if $type_selected eq 0}onchange="form.submit()"{/if}>
							{html_options options=$type_cotis_options selected=$data.id_type_cotis}
						</SELECT>
					</TD>
				</TR>
				{if $type_selected eq 1}
				<TR>
					<TH {if $required.montant_cotis eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Amount:")}</TH>
					<TD {if !$data.trans_id}colspan="3"{/if}><INPUT type="text" name="montant_cotis" value="{$data.montant_cotis}" maxlength="10"></TD>
					{if $data.trans_id}
					<TH id="libelle">{_T("Transaction number:")}</TH>
					<TD colspan="3">{$data.trans_id}</TD>
					{/if}
				</TR>
				<TR>
					<TH {if $required.date_debut_cotis eq 1}style="color: #FF0000;"{/if} id="libelle">
						{if $cotis_extension eq 0}
							{_T("Date of contribution:")}
						{else}
							{_T("Start date of membership:")}
						{/if}
						<BR>&nbsp;</TH>
					<TD {if $cotis_extension eq 0}colspan="3"{/if}>
						<INPUT type="text" name="date_debut_cotis" value="{$data.date_debut_cotis}" maxlength="10"><BR>
						<DIV class="exemple">{_T("(dd/mm/yyyy format)")}</DIV>
					</TD>
					{if $cotis_extension ne 0}
					<TH {if $required.date_fin_cotis eq 1}style="color: #FF0000;"{/if} id="libelle">
						{if $pref_membership_ext != ""}
							{_T("Membership extension:")}
						{else}
							{_T("End date of membership:")}
						{/if}
						<BR>&nbsp;
					</TH>
					<TD>
						{if $pref_membership_ext != ""}
						<INPUT type="text" name="duree_mois_cotis" value="{$data.duree_mois_cotis}" maxlength="3"><BR>
						<DIV class="exemple">{_T("months")}</DIV>
						{else}
						<INPUT type="text" name="date_fin_cotis" value="{$data.date_fin_cotis}" maxlength="10"><BR>
						<DIV class="exemple">{_T("(dd/mm/yyyy format)")}</DIV>
						{/if}
					</TD>
					{/if}
				</TR>
				<TR>
					<TH {if $required.info_cotis eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Comments:")}</TH>
					<TD colspan="3"><TEXTAREA name="info_cotis" cols="61" rows="6">{$data.info_cotis}</TEXTAREA></TD>
				</TR>
{include file="display_dynamic_fields.tpl" is_form=true}
				<TR>
					<TH align="center" colspan="4"><BR><input type="submit" value="{_T("Save")}"></TH>
				</TR>
				{else} {* $type_selected ne 1 *}
				<TR>
					<TH align="center" colspan="4"><BR><input type="submit" value="{_T("Continue")}"></TH>
				</TR>
				{/if} {* $type_selected eq 1 *}
			</TABLE>
		</DIV>
		<BR>
		{_T("NB : The mandatory fields are in")} <font style="color: #FF0000">{_T("red")}</font>.
		</BLOCKQUOTE>
		<INPUT type="hidden" name="id_cotis" value="{$data.id_cotis}">
		<INPUT type="hidden" name="trans_id" value="{$data.trans_id}">
		{if $type_selected eq 1}
		<INPUT type="hidden" name="valid" value="1">
		{else}
		<INPUT type="hidden" name="montant_cotis" value="{$data.montant_cotis}">
		{/if} {* $type_selected eq 1 *}
		<INPUT type="hidden" name="type_selected" value="1">
		<INPUT type="hidden" name="cotis_extension" value="{$cotis_extension}">
		</FORM>
