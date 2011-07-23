<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$galette_lang}" lang="{$galette_lang}">
	<head>
		{include file='common_header.tpl'}
		{if $head_redirect}{$head_redirect}{/if}
	</head>
	<body>
        <form id="login_frm" action="change_passwd.php" method="post" enctype="multipart/form-data">
            <div id="main_logo">
                <img src="{$galette_base_path}picture.php?logo=true" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" />
            </div>
            <div class="login-box">
                <h1 id="titre">{_T string="Password recovery"}</h1>
{if $error_detected|@count != 0}
                <div id="errorbox">
                    <h1>{_T string="- ERROR -"}</h1>
                    <ul>
{foreach from=$error_detected item=error}
                        <li>{$error}</li>
{/foreach}
                    </ul>
                </div>
{/if}
{if $warning_detected|@count != 0}
                <div id="warningbox">
                    <h1>{_T string="- WARNING -"}</h1>
                    <ul>
{foreach from=$warning_detected item=warning}
                        <li>{$warning}</li>
{/foreach}
                    </ul>
                </div>
{/if}
{if $password_updated}
                <div id="infobox">{_T string="Password changed, you will be redirected to login page"}</div>
{/if}
{if !$head_redirect and !$password_updated}
                <table>
                    <tr>
                        <th><label for="mdp_adh">{_T string="Password:"}</label></th>
                        <td><input type="password" name="mdp_adh" id="mdp_adh" value="" maxlength="20"/></td>
                    </tr>
                    <tr>
                        <th><label for="mdp_adh2">{_T string="Confirmation:"}</label></th>
                        <td><input type="password" name="mdp_adh2" id="mdp_adh2" value="" maxlength="20"/></td>
                    </tr>
                </table>
                <p class="exemple">{_T string="(at least 4 characters)"}</p>
                <input type="submit" class="submit" name="change_passwd" value="{_T string="Change my password"}"/>
                <input type="hidden" name="valid" value="1"/>
                <input type="hidden" name="hash" value="{$hash}"/>
{/if}
                <ul class="menu">
                    <li id="backhome"><a href="index.php">{_T string="Back to login page"}</a></li>
                    <li id="subscribe"><a href="self_adherent.php">{_T string="Subscribe"}</a></li>
                </ul>
            </div>
        </form>
		<div id="copyright">
			<a href="http://galette.tuxfamily.org/">Galette {$GALETTE_VERSION}</a>
		</div>
	</body>
</html>
