{extends file="public_page.tpl"}
{block name="content"}
        <form action="{path_for name="do-password-recovery"}" method="post" enctype="multipart/form-data">
{*if $password_updated}
                <div id="infobox">{_T string="Your password has been changed. Please click on the 'home' button to go to the login page."}</div>
{/if*}
                <table>
                    <tr>
                        <th><label for="mdp_adh">{_T string="New password:"}</label></th>
                        <td><input type="password" name="mdp_adh" id="mdp_adh" value="" maxlength="20" required="required"/></td>
                    </tr>
                    <tr>
                        <th><label for="mdp_adh2">{_T string="Confirmation:"}</label></th>
                        <td><input type="password" name="mdp_adh2" id="mdp_adh2" value="" maxlength="20" required="required"/></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="exemple">{_T string="(at least 4 characters)"}</td>
                    </tr>
                </table>
                <input type="submit" name="change_passwd" value="{_T string="Change my password"}"/>
                <input type="hidden" name="hash" value="{$hash}"/>
        </form>
{/block}
