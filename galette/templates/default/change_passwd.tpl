{html_doctype xhtml=true type=strict omitxml=false encoding=iso-8859-1}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$galette_lang}" lang="{$galette_lang}">
	<head>
		<title>Galette {$GALETTE_VERSION}</title>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"/>
		<link rel="stylesheet" type="text/css" href="{$template_subdir}galette.css"/>
		<script type="text/javascript" src="{$jquery_dir}jquery-1.2.1.pack.js"></script>
		<script type="text/javascript" src="{$jquery_dir}jquery.bgFade.js"></script>
		<script type="text/javascript" src="{$jquery_dir}jquery.corner.js"></script>
		<script type="text/javascript" src="{$jquery_dir}chili-1.7.pack.js"></script>
		<script type="text/javascript" src="{$jquery_dir}jquery.tooltip.pack.js"></script>
		<script type="text/javascript" src="{$scripts_dir}common.js"></script>
{if $head_redirect}{$head_redirect}{/if}
	</head>
	<body>
		<div id="main_logo">
{if $smarty.session.customLogo}
			<img src="photos/0.{$smarty.session.customLogoFormat}" height="{$smarty.session.customLogoHeight}" width="{$smarty.session.customLogoWidth}" alt="[ Galette ]"/>
{else}
			<img src="{$template_subdir}images/galette.png" alt="[ Galette ]" width="129" height="60"/>
{/if}
		</div>

		<div class="login-box">
			<h1 id="titre">{_T("Password recovery")}</h1>
{if $error_detected|@count != 0}
			<div id="errorbox">
				<h1>{_T("- ERROR -")}</h1>
				<ul>
{foreach from=$error_detected item=error}
					<li>{$error}</li>
{/foreach}
				</ul>
			</div>
{/if}
{if $warning_detected|@count != 0}
			<div id="warningbox">
				<h1>{_T("- WARNING -")}</h1>
				<ul>
{foreach from=$warning_detected item=warning}
					<li>{$warning}</li>
{/foreach}
				</ul>
			</div>
{/if}
{if !$head_redirect}
			<form action="change_passwd.php" method="post" enctype="multipart/form-data">
				<div class="cssform">
					<p>
						<label for="mdp_adh" class="bline required">{_T("Password:")}</label>
						<input type="password" name="mdp_adh" id="mdp_adh" value="" maxlength="20"/>
					</p>
					<p>
						<label for="mdp_adh2" class="bline required">{_T("Confirmation:")}</label>
						<input type="password" name="mdp_adh2" id="mdp_adh2" value="" maxlength="20"/>
					</p>
					<p class="exemple">{_T("(at least 4 characters)")}</p>
					<input type="submit" class="submit" name="change_passwd" value="{_T("Change my password")}"/>
					<input type="hidden" name="valid" value="1"/>
					<input type="hidden" name="hash" value="{$hash}"/>
				</div>
			</form>
{/if}
			<ul class="menu">
				<li id="subscribe"><a href="self_adherent.php">{_T("Subscribe")}</a></li>
				<li id="backhome"><a href="index.php">{_T("Back to login page")}</a></li>
			</ul>
		</div>
		<div id="copyright">
			<a href="http://galette.tuxfamily.org/">Galette {$GALETTE_VERSION}</a>
		</div>
	</body>
</html>
