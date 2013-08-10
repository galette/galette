        <form class="form" id="send_reminders" action="reminder.php" method="post" enctype="multipart/form-data">
            <fieldset>
                <legend class="ui-state-active ui-corner-top">{_T string="Choose wich reminder(s) you want to send:"}</legend>
                <div>
                    <ul>
                        <li{if $count_impending eq 0 and $count_impending_nomail eq 0} class="disabled"{/if}>
                            <input type="checkbox" name="reminders[]" id="reminder_impending" value="{php}echo \Galette\Entity\Reminder::IMPENDING;{/php}"{if $count_impending eq 0 and $count_impending_nomail eq 0} disabled="disabled"{/if}/>
                            <label for="reminder_impending">{_T string="Impending due date"}</label>
                            <a class="show_previews" id="impending" href="#impending_preview">({_T string="preview"})</a> -
                            <a href="gestion_adherents.php?filter_membership=1&filter_account=1&email_filter=6">{_T string="%s members with mail" pattern="/%s/" replace=$count_impending}</a>
                            <a href="gestion_adherents.php?filter_membership=1&filter_account=1&email_filter=7">{_T string="%s members without mail" pattern="/%s/" replace=$count_impending_nomail}</a>
                        </li>
                        <li{if $count_late eq 0 and $count_late_nomail eq 0} class="disabled"{/if}>
                            <input type="checkbox" name="reminders[]" id="reminder_late" value="{php}echo \Galette\Entity\Reminder::LATE;{/php}"{if $count_late eq 0 and $count_late_nomail eq 0} disabled="disabled"{/if}/>
                            <label for="reminder_late">{_T string="Late"}</label>
                            <a class="show_previews" id="late" href="#impending_preview">({_T string="preview"})</a> -
                            <a href="gestion_adherents.php?filter_membership=2&filter_account=1&email_filter=6">{_T string="%s members with mail" pattern="/%s/" replace=$count_late}</a>
                            <a href="gestion_adherents.php?filter_membership=2&filter_account=1&email_filter=7">{_T string="%s members without mail" pattern="/%s/" replace=$count_late_nomail}</a>
                        </li>
                        <li{if $count_impending_nomail eq 0 and $count_late_nomail eq 0} class="disabled"{/if}>
                            <input type="checkbox" name="reminder_wo_mail" id="reminder_wo_mail" value="1"{if $count_impending_nomail eq 0 and $count_late_nomail eq 0} disabled="disabled"{/if}/>
                            <label for="reminder_wo_mail">{_T string="Generate labels for late members without mail address"}</label>
                        </li>
                    </ul>
                </div>
            </fieldset>
            <div class="button-container">
                <input id="btnsend" type="submit" name="valid" value="{_T string="Send"}"/>
            </div>
        </form>
{foreach from=$previews key=key item=preview}
        <div id="{$key}_preview" title="{$preview->tcomment}" class="preview">
            <div>
                <p>
                    <span class="bline">{_T string="Subject:"}</span>
                    <span>{$preview->tsubject}</span>
                </p>
                <p>
                    <span class="bline">{_T string="Message:"}</span>
                    <span>{$preview->tbody|nl2br}</span>
                </p>
            </div>
        </div>
{/foreach}
        <script type="text/javascript">
            $(function(){
                $('.preview').hide().dialog({
                    autoOpen: false
                });
                $('.show_previews').click(function(){
                    $('#' + $(this).attr('id') + '_preview').dialog('open');
                    return false;
                });
                $('#send_reminders').submit(function(){
                    var _this = $(this);
                    var _checkeds = _this.find('input[type=checkbox]:checked').length;

                    if ( _checkeds == 0 ) {
                        var _el = $('<div id="pleaseselect" title="{_T string="No reminder selected" escape="js"}">{_T string="Please make sure to select at least one reminder." escape="js"}</div>');
                        _el.appendTo('body').dialog({
                            modal: true,
                            buttons: {
                                Ok: function() {
                                    $(this).dialog( "close" );
                                }
                            },
                            close: function(event, ui){
                                _el.remove();
                            }
                        });
                        return false;
                    } else {
                        return true;
                    }
                });
            });
        </script>
