{extends file="page.tpl"}

{block name="content"}
        <form id="send_reminders" action="{path_for name="doReminders"}" method="post" enctype="multipart/form-data" class="ui form">
            <div class="ui top attached header">
                {_T string="Choose wich reminder(s) you want to send:"}
            </div>
            <div class="ui bottom attached segment">
                <div class="active content field">
                    <div class="inline field{if $count_impending eq 0 and $count_impending_nomail eq 0} disabled{/if}">
                        <input type="checkbox" name="reminders[]" id="reminder_impending" value="{\Galette\Entity\Reminder::IMPENDING}"{if $count_impending eq 0 and $count_impending_nomail eq 0} disabled="disabled"{/if}/>
                        <label for="reminder_impending">{_T string="Impending due date"}</label>
                        <a class="show_previews" id="impending" href="#impending_preview">({_T string="preview"})</a> -
                        <a href="{path_for name="reminders-filter" data=["membership" => "nearly", "mail" => "withmail"]}">{_T string="%s members with an email address" pattern="/%s/" replace=$count_impending}</a>
                        <a href="{path_for name="reminders-filter" data=["membership" => "nearly", "mail" => "withoutmail"]}">{_T string="%s members without email address" pattern="/%s/" replace=$count_impending_nomail}</a>
                    </div>
                    <div class="inline field{if $count_late eq 0 and $count_late_nomail eq 0} disabled{/if}">
                        <input type="checkbox" name="reminders[]" id="reminder_late" value="{\Galette\Entity\Reminder::LATE}"{if $count_late eq 0 and $count_late_nomail eq 0} disabled="disabled"{/if}/>
                        <label for="reminder_late">{_T string="Late"}</label>
                        <a class="show_previews" id="late" href="#impending_preview">({_T string="preview"})</a> -
                        <a href="{path_for name="reminders-filter" data=["membership" => "late", "mail" => "withmail"]}">{_T string="%s members with an email address" pattern="/%s/" replace=$count_late}</a>
                        <a href="{path_for name="reminders-filter" data=["membership" => "late", "mail" => "withoutmail"]}">{_T string="%s members without email address" pattern="/%s/" replace=$count_late_nomail}</a>
                    </div>
                    <div class="inline field{if $count_impending_nomail eq 0 and $count_late_nomail eq 0} disabled{/if}">
                        <input type="checkbox" name="reminder_wo_mail" id="reminder_wo_mail" value="1"{if $count_impending_nomail eq 0 and $count_late_nomail eq 0} disabled="disabled"{/if}/>
                        <label for="reminder_wo_mail">{_T string="Generate labels for members without email address"}</label>
                    </div>
                </div>
            </div>
            <div class="ui basic center aligned segment">
                <button type="submit" name="valid" class="ui labeled icon button">
                    <i class="rocket icon" aria-hidden="true"></i>
                    {_T string="Send"}
                </button>
                {include file="forms_types/csrf.tpl"}
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
{/block}

{block name="javascripts"}
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
{/block}
