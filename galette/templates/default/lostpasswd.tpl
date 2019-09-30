{extends file="public_page.tpl"}
{assign var=body_class value="front_page"}
{block name="content"}
                <form action="{path_for name="retrieve-pass"}" method="post" enctype="multipart/form-data" class="ui form">
                    <div class="ui segment">
                        <div class="field">
                            <div class="ui left icon input">
                                <i class="user icon"></i><span class="hidden"><label for="login">{_T string="Username or email:"}</label></span>
                                <input type="text" name="login" id="login" autofocus placeholder="{_T string="Username or email:"}"/>
                            </div>
                        </div>
                        <input type="submit" class="ui fluid large primary submit button" name="lostpasswd" value="{_T string="Recover password"}" />
                        <input type="hidden" name="valid" value="1"/>
                        {include file="forms_types/csrf.tpl"}
                    </div>
                </form>
{/block}
