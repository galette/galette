{extends file="public_page.tpl"}
{block name="content"}
            <form action="{path_for name="get-directlink" data=["hash" => $hash]}" method="post" enctype="multipart/form-data">
                <section>
                    <p>
                        <label for="login" class="">{_T string="Please confirm your email address:"}</label>
                        <input type="email" name="email" id="email" maxlength="50" required="required" />
                    </p>
                    <input type="submit" name="directlink" value="{_T string="Get my document"}" />
                    <input type="hidden" name="valid" value="1"/>
                    <input type="hidden" name="hash" value="{$hash}"/>
                    {include file="forms_types/csrf.tpl"}
                </section>
            </form>
{/block}
