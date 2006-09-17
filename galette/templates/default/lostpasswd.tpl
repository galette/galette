{html_doctype xhtml=true type=strict omitxml=false encoding=iso-8859-1}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
<head>
	<title>Galette {$GALETTE_VERSION}</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"/>
	<link rel="stylesheet" type="text/css" href="{$template_subdir}galette.css"/>
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
		<form action="lostpasswd.php" method="post" enctype="multipart/form-data">
		<table>
			<tr>
				<th>{_T("Username or email:")}</th>
				<td><input type="text" name="login" maxlength="50" /></td>
			</tr>
		</table>
		<br/>
		<input type="submit" class="submit" name="lostpasswd" value="{_T("Send me my password")}" />
		<input type="hidden" name="valid" value="1"/>
		<br/><br/>
		</form>
	</div>
	<div class="button-container">
		<div class="button-link button-back">
			<a href="index.php">{_T("Back to login page")}</a>
		</div>
	</div>
</body>
</html>
