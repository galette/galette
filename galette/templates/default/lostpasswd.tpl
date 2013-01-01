                <form action="lostpasswd.php" method="post" enctype="multipart/form-data">
                <section>
                    <p>
                        <label for="login" class="">{_T string="Username or email:"}</label>
                        <input type="text" name="login" id="login" maxlength="50" />
                    </p>
                    <input type="submit" name="lostpasswd" value="{_T string="Recover password"}" />
                    <input type="hidden" name="valid" value="1"/>
                </section>
                </form>
