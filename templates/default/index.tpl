<?xml version="1.0" encoding="ISO-8859-1"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
<head> 
	<title>Galette {$galette_version}</title> 
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" /> 
	<link rel="stylesheet" type="text/css" href="{$template_subdir}galette.css" /> 
</head> 
<body style="backgound-color:#FFFFFF">
	<table width="100%" style="height: 100%">
		<tr>
			<td align="center">
				<img src="{$template_subdir}images/galette.png" alt="[ Galette ]" width="129" height="60" /><br /><br /><br />
				<br />
				<form action="index.php" method="post"> 
					<p class="titre">{_T("Login")}</p>
        <p>
				{foreach key=langue item=langue_t from=$languages}
				<a href="index.php?pref_lang={$langue}"><img src="lang/{$langue}.gif" alt="{$langue_t}" /></a>
				{/foreach}
        </p>
					<table> 
						<tr> 
							<td>{_T("Username:")}</td> 
							<td><input type="text" name="login" /></td> 
						</tr> 
						<tr> 
							<td>{_T("Password:")}</td> 
							<td><input type="password" name="password" /></td> 
						</tr> 
					</table>
          <div>
            <input type="submit" value="{_T("Login")}" /><br />
            <a href="lostpasswd.php">{_T("Lost your password?")}</a><br />
            <a href="self_adherent.php">{_T("Subscribe")}</a><br />
            <input type="hidden" name="ident" value="1" />
          </div>
				</form>
			</td>
		</tr>
	</table> 
</body>
</html>
