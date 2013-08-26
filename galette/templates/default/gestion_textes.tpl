        <form action="gestion_textes.php" method="post" enctype="multipart/form-data"> 
        <div class="bigtable">
            <fieldset class="cssform" id="{$mtxt->tlang}">
                <legend class="ui-state-active ui-corner-top">{$mtxt->tcomment}</legend>
                <p>
                    <label for="sel_lang" class="bline">{_T string="Language:"}</label>
                    <select name="sel_lang" id="sel_lang">
                        {foreach item=langue from=$langlist}
                            <option value="{$langue->getID()}" {if $cur_lang eq $langue->getID()}selected="selected"{/if} style="padding-left: 30px; background-image: url({$langue->getFlag()}); background-repeat: no-repeat">{$langue->getName()}</option>
                        {/foreach}
                    </select>
                    <noscript> <span><input type="submit" name="change_lang" value="{_T string="Change"}" /></span></noscript>
                </p>
                <p>
                    <label for="sel_ref" class="bline">{_T string="Reference:"}</label>
                    <select name="sel_ref" id="sel_ref">
                        {foreach item=ref from=$reflist}
                            <option value="{$ref.tref}" {if $cur_ref eq $ref.tref}selected="selected"{/if} >{$ref.tcomment}</option>
                        {/foreach}
                    </select>
                    <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
                </p>
                <p>
                    <label for="tsubject" class="bline">{_T string="Email Subject"}</label> 
                    <input type="text" name="text_subject" id="tsubject" value="{$mtxt->tsubject}" maxlength="255" size="32"/> <span class="exemple">{_T string="(Max 255 characters)"}</span>
                </p>
                <p>
                    <label for="text_body" class="bline">{_T string="Email Body:"}</label>
                    <textarea name="text_body" id="text_body" cols="64" rows="15">{$mtxt->tbody}</textarea>
                </p>
            </fieldset>
        </div>
        <div class="button-container">
            <input type="hidden" name="valid" id="valid" value="1"/>
            <input type="submit" value="{_T string="Save"}"/>
        </div>
        </form>
        <div id="legende" class="texts_legend" title="{_T string="Existing variables"}">
            <h1>{_T string="Existing variables"}</h1>
            <table>
                <tr>
                    <th><tt>{ldelim}ASSO_NAME{rdelim}</tt></th>
                    <td class="back">{_T string="Your organisation name"}<br/><span>({_T string="globally available"})</span></td>
                    <th class="back"><tt>{ldelim}ASSO_SLOGAN{rdelim}</tt></th>
                    <td class="back">{_T string="Your organisation slogan"}<br/><span>({_T string="globally available"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}NAME_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's first and last name"}<br/><span>({_T string="available with reservations"})</span></td>
                    <th class="back"><tt>{ldelim}MAIL_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's email address"}<br/><span>({_T string="available with reservations"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}LASTNAME_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's last name"}<br/><span>({_T string="available with reservations"})</span></td>
                    <th><tt>{ldelim}FIRSTNAME_ADH{rdelim}</tt></th>
                    <td class="back">{_T string="Member's first name"}<br/><span>({_T string="available with reservations"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}LOGIN{rdelim}</tt></th>
                    <td class="back">{_T string="Member's login"}<br/><span>({_T string="available with reservations"})</span></td>
                    <th><tt>{ldelim}LOGIN_URI{rdelim}</tt></th>
                    <td class="back">{_T string="Galette's login URI"}<br/><span>({_T string="globally available"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}PASSWORD{rdelim}</tt></th>
                    <td class="back">{_T string="Member's password"}<br/><span>({_T string="available only from self subscribe page"})</span></td>
                    <th><tt>{ldelim}CHG_PWD_URI{rdelim}</tt></th>
                    <td class="back">{_T string="Galette's change password URI"}<br/><span>({_T string="available only for new password request"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}LINK_VALIDITY{rdelim}</tt></th>
                    <td class="back">{_T string="Link validity"}<br/><span>({_T string="available only for new password request"})</span></td>
                    <th><tt>{ldelim}DEADLINE{rdelim}</tt></th>
                    <td class="back">{_T string="Member's deadline"}<br/><span>({_T string="available only for new contributions"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}CONTRIB_INFO{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution informations"}<br/><span>({_T string="available only for new contributions"})</span></td>
                    <th><tt>{ldelim}CONTRIB_AMOUNT{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution amount"}<br/><span>({_T string="available only for new contributions"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}CONTRIB_TYPE{rdelim}</tt></th>
                    <td class="back">{_T string="Contribution type"}<br/><span>({_T string="available only for new contributions"})</span></td>
                    <th><tt>{ldelim}DAYS_REMAINING{rdelim}</tt></th>
                    <td class="back">{_T string="Membership remaining days"}<br/><span>({_T string="available only for reminders"})</span></td>
                </tr>
                <tr>
                    <th><tt>{ldelim}DAYS_EXPIRED{rdelim}</tt></th>
                    <td class="back">{_T string="Membership expired since"}<br/><span>({_T string="available only for reminders"})</span></td>
                    <th>&nbsp;</th>
                    <td class="back">&nbsp;</td>
                </tr>
        </table>
        </div>
        <script type="text/javascript">
            $(function() {
                $('#sel_ref, #sel_lang').change(function() {
                    $(':input[type="submit"]').attr('disabled', 'disabled');
                    //Change the input[@id='value'] ; we do not want to validate, but to change lang/ref
                    $('#valid').attr('value', (this.id === 'sel_lang') ? 'change_lang' : 'change_text');
                    this.form.submit();
                });

                $('.cssform').prepend('<div class="fright"><a href="#" id="show_legend" class="help">{_T string="Show existing variables"}</a></div>');
                $('#legende h1').remove();
                $('#legende').dialog({
                    autoOpen: false,
                    modal: true,
                    hide: 'fold',
                    width: '40%'
                }).dialog('close');

                $('#show_legend').click(function(){
                    $('#legende').dialog('open');
                        return false;
                });
            });
        </script>
