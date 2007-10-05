{html_doctype xhtml=true type=strict omitxml=false encoding=iso-8859-1}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$galette_lang}" lang="{$galette_lang}">
<head> 
	<title>Galette {$galette_version}</title> 
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" /> 
	<link rel="stylesheet" type="text/css" href="{$template_subdir}galette.css" /> 
</head> 
<body>
	<form action="index.php" method="post">
		<div class="login-box">
  {if $smarty.session.customLogo}
  <img src="photos/0.{$smarty.session.customLogoFormat}" height="{$smarty.session.customLogoHeight}" width="{$smarty.session.customLogoWidth}" alt="[ Galette ]"/>
  {/if}
<h1 class="titre">{_T("Login")}</h1>
{foreach key=langue item=langue_t from=$languages}
		<a href="index.php?pref_lang={$langue}"><img src="lang/{$langue}.gif" alt="{$langue_t}" class="flag"/></a>
{/foreach}
		<br/><br/>
		<table> 
			<tr> 
				<th><label for="login">{_T("Username:")}</label></th> 
				<td><input type="text" name="login" id="login" /></td> 
			</tr> 
			<tr> 
				<th><label for="password">{_T("Password:")}</label></th> 
				<td><input type="password" name="password" id="password"/></td> 
			</tr> 
		</table>
		<br/>
		<input type="submit" class="submit" value="{_T("Login")}" />
		<input type="hidden" name="ident" value="1" />
	</div>
	</form>
	
	<div class="button-container">
		<div class="button-link button-subscribe">
			<a href="self_adherent.php">{_T("Subscribe")}</a>
		</div>
		<div class="button-link button-lost-password">
			<a href="lostpasswd.php">{_T("Lost your password?")}</a>
		</div>
	</div>
</body>
</html>
