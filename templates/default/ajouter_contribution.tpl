		<H1 class="titre">{_T("Contribution card")} ({if $contribution.id_cotis != ""}{_T("modification")}{else}{_T("creation")}{/if})</H1>
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
							<OPTION>{_T("-- select a name --")}</OPTION>
							{html_options options=$adh_options selected=$contribution.id_adh}
						</SELECT>
					</TD> 
					<TH {if $required.id_type_cotis eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Contribution type:")}</TH> 
					<TD>
						<SELECT name="id_type_cotis">
							{html_options options=$type_cotis_options selected=$contribution.id_type_cotis}
						</SELECT>
					</TD> 
				</TR>
				<TR>
					<TH {if $required.montant_cotis eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Amount:")}</TH> 
					<TD><INPUT type="text" name="montant_cotis" value="{$contribution.montant_cotis}" maxlength="10"></TD> 
					<TH {if $required.duree_mois_cotis eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Membership extension:")}</TH> 
					<TD><input type="text" name="duree_mois_cotis" value="{$contribution.duree_mois_cotis}" maxlength="3"> {_T("months")}</TD>
				</TR>
				<TR> 
					<TH {if $required.date_debut_cotis eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Date of contribution:")}<BR>&nbsp;</TH> 
					<TD colspan="3">
						<INPUT type="text" name="date_debut_cotis" value="{$contribution.date_debut_cotis}" maxlength="10"><BR>
						<DIV class="exemple">{_T("(dd/mm/yyyy format)")}</DIV>
					</TD> 
				</TR> 
				<TR> 
					<TH {if $required.info_cotis eq 1}style="color: #FF0000;"{/if} id="libelle">{_T("Comments:")}</TH> 
					<TD colspan="3"><TEXTAREA name="info_cotis" cols="61" rows="6">{$contribution.info_cotis}</TEXTAREA></TD> 
				</TR> 
				<TR> 
					<TH align="center" colspan="4"><BR><input type="submit" value="{_T("Save")}"></TH> 
				</TR> 
			</TABLE> 
		</DIV>
		<BR> 
		{_T("NB : The mandatory fields are in")} <font style="color: #FF0000">{_T("red")}</font>. 
		</BLOCKQUOTE> 
		<INPUT type="hidden" name="id_cotis" value="{$contribution.id_cotis}">
		<INPUT type="hidden" name="valid" value="1">
		</FORM>
