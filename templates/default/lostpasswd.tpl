<?xml version="1.0" encoding="ISO-8859-15"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
<head>
	<title>Galette {$GALETTE_VERSION}</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"/>
	<link rel="stylesheet" type="text/css" href="{$template_subdir}galette.css"/>
</head>
<body bgcolor="#FFFFFF">
	<table width="100%" style="height: 100%">
		<tr>
			<td align="center">
				<img src="{$template_subdir}images/galette.png" alt="[ Galette ]" width="129" height="60" /><br /><br /><br />
      </td>
    </tr>
  </table>
	<div id="content">
		<h1 class="titre">{_T("Password recovery")}</h1>
		<form action="lostpasswd.php" method="post" enctype="multipart/form-data"> 
{if $error_detected|@count != 0}
		<div id="errorbox">
			<h1>{_T("- ERROR -")}</h1>
			<ul>
{foreach from=$error_detected item=error}
				<li>{$error}<li>
{/foreach}
			</ul>
		</div>
{/if}
{if $warning_detected|@count != 0}
		<div id="warningbox">
			<h1>{_T("- WARNING -")}</h1>
			<ul>
{foreach from=$warning_detected item=warning}
				<li>{$warning}<li>
{/foreach}
			</ul>
		</div>
{/if}
		<blockquote>
		<div align="center">
			<table border="0" id="input-table">
				<tr> 
					<th style="color: #FF0000;" class="libelle">{_T("Username:")}</th>
					<td><input type="text" name="login" maxlength="20"/></td>
				</tr>
			</table>
			<input type="submit" name="lostpasswd" value="{_T("Send me my password")}"/>
		  <input type="hidden" name="valid" value="1"/>
		</form> 
		<form action="index.php" method="get">
      <input type="submit" name="lostpasswd" value="{_T("Back to login page")}">
    </form>
		</div>
		<br /> 
		</blockquote>
		{_T("NB : The mandatory fields are in")} <font style="color: #FF0000">{_T("red")}</font>. 
		<div id="copyright">
			<a href="http://galette.tuxfamily.org/fr" target="_blank">Galette {$GALETTE_VERSION}</a>
		</div>
	</div>
</body>
</html>
