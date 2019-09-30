{extends file="public_page.tpl"}
{assign var=body_class value="front_page"}
{block name="content"}
        <form action="{path_for name="do-password-recovery"}" method="post" enctype="multipart/form-data" class="ui form">
            <div class="ui segment">
                <div class="field">
                    <div class="ui left icon input">
                        <i class="lock icon"></i><span class="hidden"><label for="mdp_adh">{_T string="New password:"}</label></span>
                        <input type="password" name="mdp_adh" id="mdp_adh" value="" required="required"/>
                    </div>
                </div>
                <div class="field">
                    <div class="ui left icon input">
                        <i class="lock icon"></i><span class="hidden"><label for="mdp_adh2">{_T string="Confirmation:"}</label></span>
                        <input type="password" name="mdp_adh2" id="mdp_adh2" value="" required="required"/>
                    </div>
                </div>
                <p class="ui orange center aligned message">{_T string="(at least 4 characters)"}</p>
                <input type="submit" name="change_passwd" value="{_T string="Change my password"}" class="ui button"/>
                <input type="hidden" name="hash" value="{$hash}"/>
                {include file="forms_types/csrf.tpl"}
            </div>
        </form>
{/block}
