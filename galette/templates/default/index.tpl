{html_doctype xhtml=true type=strict omitxml=false encoding=UTF-8}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$galette_lang}" lang="{$galette_lang}">
	<head>
		{include file='common_header.tpl'}
	</head>
	<body>
		<form action="index.php" method="post" id="login_frm">
			<div id="main_logo">
				<img src="{$galette_base_path}picture.php?logo=true" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" />
			</div>
			<div class="login-box">
				<h1 id="titre">{_T string="Login"}</h1>
{if $loginfault}
				<div id="errorbox">{_T string="Login failed."}</div>
{/if}
				<ul id="langs">
{foreach item=langue from=$languages}
					<li><a href="index.php?pref_lang={$langue->getID()}"><img src="{$langue->getFlag()}" alt="{$langue->getName()}" lang="{$langue->getAbbrev()}" class="flag"/></a></li>
{/foreach}
				</ul>
				<table>
					<tr>
						<th><label for="login">{_T string="Username:"}</label></th>
						<td><input type="text" name="login" id="login" /></td>
					</tr>
					<tr>
						<th><label for="password">{_T string="Password:"}</label></th>
						<td><input type="password" name="password" id="password"/></td>
					</tr>
				</table>
				<input type="submit" class="submit" value="{_T string="Login"}" />
				<input type="hidden" name="ident" value="1" />
				<ul class="menu">
					<li id="subscribe"><a href="self_adherent.php">{_T string="Subscribe"}</a></li>
					<li id="lostpassword"><a href="lostpasswd.php">{_T string="Lost your password?"}</a></li>
				</ul>
			</div>
		</form>
		<div id="copyright">
			<a href="http://galette.tuxfamily.org/">Galette {$GALETTE_VERSION}</a>
		</div>
	</body>
</html>
