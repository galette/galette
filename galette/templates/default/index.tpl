<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"> 
<HTML> 
<HEAD> 
	<TITLE>Galette {$galette_version}</TITLE> 
	<META http-equiv="Content-Type" content="text/html; charset=iso-8859-1"> 
	<LINK rel="stylesheet" type="text/css" href="{$template_subdir}galette.css"> 
</HEAD> 
<BODY bgcolor="#FFFFFF">
	<TABLE width="100%" style="height: 100%">
		<TR>
			<TD align="center">
				<IMG src="{$template_subdir}images/galette.png" alt="[ Galette ]" width="129" height="60"><BR><BR><BR>
				{foreach key=langue item=langue_t from=$languages}
				<A href="index.php?pref_lang={$langue}"><IMG src="lang/{$langue}.gif" alt="{$langue_t}"></A>
				{/foreach}
				<BR>
				<FORM action="index.php" method="post"> 
					<B class="title">{_T("Login")}</B><BR>
					<BR>
					<BR>
					<TABLE> 
						<TR> 
							<TD>{_T("Username:")}</TD> 
							<TD><INPUT type="text" name="login"></TD> 
						</TR> 
						<TR> 
							<TD>{_T("Password:")}</TD> 
							<TD><INPUT type="password" name="password"></TD> 
						</TR> 
					</TABLE>
					<INPUT type="submit" value="{_T("Login")}"><BR>
					<BR>
					<A HREF="lostpasswd.php">{_T("Lost your password?")}</a>
				        <BR>
					<A href="self_adherent.php">{_T("Subscribe")}</A>
					<INPUT type="hidden" name="ident" value="1">
				</FORM>
			</TD>
		</TR>
	</TABLE> 
</BODY>
</HTML>
