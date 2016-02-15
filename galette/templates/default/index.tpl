{extends file="public_page.tpl"}
{block name="content"}
    {if isset($smarty.session['slim.flash']['loginfault'])}
                <div id="errorbox">{$smarty.session['slim.flash']['loginfault']}</div>
    {/if}
                <form action="{path_for name="dologin"}" method="post">
                <section>
                    <table>
                        <tr>
                            <th><label for="login">{_T string="Username:"}</label></th>
                            <td><input type="text" name="login" id="login" autofocus/></td>
                        </tr>
                        <tr>
                            <th><label for="password">{_T string="Password:"}</label></th>
                            <td><input type="password" name="password" id="password"/></td>
                        </tr>
                    </table>
                    <input type="submit" value="{_T string="Login"}" />
                    <input type="hidden" name="ident" value="1" />
                </section>
                </form>
{/block}
