{if $loginfault}
                <div id="errorbox">{_T string="Login failed."}</div>
{/if}
                <form action="index.php" method="post">
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
