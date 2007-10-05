{html_doctype xhtml=true type=strict omitxml=false encoding=iso-8859-1}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$galette_lang}" lang="{$galette_lang}">
<head>
	<title>Galette {$GALETTE_VERSION}</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"/>
	<link rel="stylesheet" type="text/css" href="{$template_subdir}galette.css"/>
{if $head_redirect}{$head_redirect}{/if}
</head>
<body>
	<div class="login-box">
		<h1 class="titre">{_T("Password recovery")}</h1>
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
		<table>
			<tr>
				<th {if $required.mdp_adh eq 1}style="color: #FF0000;"{/if} class="libelle">{_T("Password:")}<br/>&nbsp;</th> 
				<td colspan="3">
					<input type="password" name="mdp_adh" value="" maxlength="20"/>
					<div class="exemple">{_T("(at least 4 characters)")}</div><br/>
					<input type="password" name="mdp_adh2" value="" maxlength="20"/>
					<div class="exemple">{_T("(Confirmation)")}</div><br/>
				</td>
			</tr>
		</table>
		<div>
			<input type="submit" class="submit" name="change_passwd" value="{_T("Change my  password")}"/>
			<input type="hidden" name="valid" value="1"/>
			<input type="hidden" name="hash" value="{$hash}"/>
		</div>
		</form>
{/if}
	</div>
	<div class="button-container">
		<div class="button-link button-back">
			<a href="index.php">{_T("Back to login page")}</a>
		</div>
	</div>
</body>
</html>
