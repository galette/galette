{extends file="public_page.tpl"}
{assign var=body_class value="front_page"}
{block name="content"}
    {if isset($smarty.session['slim.flash']['loginfault'])}
                <div class="ui error tiny message">{$smarty.session['slim.flash']['loginfault']}</div>
    {/if}
                <form action="{path_for name="dologin"}" method="post" class="ui form">
    {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED')}
                    <div class="ui segments">
    {/if}
                        <div class="ui segment">
                            <div class="field">
                                <div class="ui left icon input">
                                    <i class="user icon"></i><span class="hidden"><label for="login">{_T string="Username:"}</label></span>
                                    <input type="text" name="login" id="login" autofocus placeholder="{_T string="Username:"}"/>
                                </div>
                            </div>
                            <div class="field">
                                <div class="ui left icon input">
                                    <i class="lock icon"></i><span class="hidden"><label for="password">{_T string="Password:"}</label></span>
                                    <input type="password" name="password" id="password" placeholder="{_T string="Password:"}"/>
                                </div>
                            </div>
                            <input type="submit" class="ui fluid large primary submit button" value="{_T string="Login"}"/>
                            <input type="hidden" name="ident" value="1" />
                            {include file="forms_types/csrf.tpl"}
                        </div>
    {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED')}
                        <div class="ui center aligned secondary segment">
                            <a
                                href="{path_for name="password-lost"}"
                                class="password-lost"
                                title="{_T string="Lost your password?"}"
                            >
                                <i class="icon unlock alt" aria-hidden="true"></i>
                                {_T string="Lost your password?"}
                            </a>
                        </div>
                    </div>
    {/if}
                </form>
{/block}
