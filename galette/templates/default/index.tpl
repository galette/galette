{html_doctype xhtml=true type=strict omitxml=false encoding=iso-8859-1}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$galette_lang}" lang="{$galette_lang}">
	<head>
		<title>{if $pref_slogan ne ""}{$pref_slogan} - {/if}{if $page_title ne ""}{$page_title} - {/if}Galette {$GALETTE_VERSION}</title>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
		<link rel="stylesheet" type="text/css" href="{$template_subdir}galette.css" />
		<script type="text/javascript" src="{$jquery_dir}jquery-1.2.1.pack.js"></script>
		<script type="text/javascript" src="{$jquery_dir}jquery.bgFade.js"></script>
		<script type="text/javascript" src="{$jquery_dir}jquery.corner.js"></script>
		<script type="text/javascript" src="{$scripts_dir}common.js"></script>
	</head>
	<body>
		<form action="index.php" method="post">
			<div id="main_logo">
{if $smarty.session.customLogo}
				<img src="photos/0.{$smarty.session.customLogoFormat}" height="{$smarty.session.customLogoHeight}" width="{$smarty.session.customLogoWidth}" alt="[ Galette ]"/>
{else}
				<img src="{$template_subdir}images/galette.png" alt="[ Galette ]" width="129" height="60"/>
{/if}
			</div>
			<div class="login-box">
				<h1 id="titre">{_T("Login")}</h1>
{if $loginfault}
				<div id="errorbox">{_T("Login failed.")}</div>
{/if}
				<ul id="langs">
{foreach item=langue from=$languages}
					<li><a href="index.php?pref_lang={$langue->getID()}"><img src="{$langue->getFlag()}" alt="{$langue->getName()}" lang="{$langue->getAbbrev()}" class="flag"/></a></li>
{/foreach}
				</ul>
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
				<input type="submit" class="submit" value="{_T("Login")}" />
				<input type="hidden" name="ident" value="1" />
				<ul class="menu">
					<li id="subscribe"><a href="self_adherent.php">{_T("Subscribe")}</a></li>
					<li id="lostpassword"><a href="lostpasswd.php">{_T("Lost your password?")}</a></li>
				</ul>
			</div>
		</form>
		<div id="copyright">
			<a href="http://galette.tuxfamily.org/">Galette {$GALETTE_VERSION}</a>
		</div>
	</body>
</html>
