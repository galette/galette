{html_doctype xhtml=true type=strict omitxml=false encoding=iso-8859-1}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
<head>
	<title>Galette {$GALETTE_VERSION}</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"/>
	<link rel="stylesheet" type="text/css" href="{$template_subdir}galette.css"/>
</head>
<body style="backgound-color:#FFFFFF">
	<table width="100%" style="height: 100%">
		<tr>
			<td align="center">
				<img src="{$template_subdir}images/galette.png" alt="[ Galette ]" width="129" height="60" /><br /><br /><br />
      </td>
    </tr>
  </table>
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
        <div style="position:relative;padding-left:40%">
          <table border="0" id="input-table">
            <tr>
              <th style="color: #FF0000;" class="libelle">{_T("Username:")}</th>
              <td><input type="text" name="login" maxlength="20" /></td>
            </tr>
          </table>
          <input type="submit" name="lostpasswd" value="{_T("Send me my password")}" />
          <input type="hidden" name="valid" value="1"/>
	<p>{_T("NB : The mandatory fields are in")} <span style="color: #FF0000">{_T("red")}</span></p>
        </div>
      </form>
	<div class="button-container">
		<div class="button-link button-back">
			<a href="index.php">{_T("Back to login page")}</a>
		</div>
	</div>
</body>
</html>
