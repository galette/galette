{if $pref_mail_method == constant('Galette\Core\Mailing::METHOD_DISABLED') and $GALETTE_MODE neq 'DEMO'}
        <div id="errorbox">
            <h1>{_T string="- ERROR -"}</h1>
            <p>{_T string="Email sent is disabled in the preferences. Ask galette admin"}</p>
        </div>
{elseif !isset($mailing_saved)}
        <form action="mailing_adherents.php#mail_preview" id="listform" method="post" enctype="multipart/form-data">
        <div class="mailing">
            <section class="mailing_infos">
                <header class="ui-state-default ui-state-active">{_T string="Mailing informations"}</header>
    {assign var='count' value=$mailing->recipients|@count}
    {assign var='count_unreachables' value=$mailing->unreachables|@count}
    {if $count > 0}
        {if $mailing->current_step eq constant('Galette\Core\Mailing::STEP_SENT')}
                <p>{_T string="Your message has been sent to <strong>%s members</strong>" pattern="/%s/" replace=$count}</p>
        {else}
                <p id="recipients_count">{_T string="You are about to send an e-mail to <strong>%s members</strong>" pattern="/%s/" replace=$count}</p>
        {/if}
    {else}
        {if $count_unreachables > 0}
                <p id="recipients_count"><strong>{_T string="None of the selected members has an email address."}</strong></p>
         {else}
                <p id="recipients_count"><strong>{_T string="No member selected (yet)."}</strong></p>
         {/if}
    {/if}
    {if $count_unreachables > 0}
                <p id="unreachables_count">
                    <strong>{$count_unreachables} {if $count_unreachables != 1}{_T string="unreachable members:"}{else}{_T string="unreachable member:"}{/if}</strong><br/>
                    {_T string="Some members you have selected have no e-mail address. However, you can generate envelope labels to contact them by snail mail."}
                    <br/><a id="btnlabels" class="button" href="etiquettes_adherents.php?from=mailing">{_T string="Generate labels"}</a>
                </p>
    {/if}

                <div class="center">
    {if $mailing->current_step eq constant('Galette\Core\Mailing::STEP_SENT')}
                    <a class="button" id="btnusers" href="gestion_adherents.php">{_T string="Go back to members list"}</a>
    {else}
                    <a class="button" id="btnusers" href="gestion_adherents.php?nbshow=0&showChecked=true">{_T string="Manage selected members"}</a>
    {/if}
                </div>
            </section>
        {if $mailing->current_step eq constant('Galette\Core\Mailing::STEP_START')}
            <section class="mailing_attachments">
                <header class="ui-state-default ui-state-active">{_T string="Attachments"}</header>
                <div>
                    {if $attachments|@count gt 0}
                    <p class="bline">
                        {_T string="Existing attachments:"}
                        <ul id="existing_attachments">
                            {foreach item=attachment from=$attachments}
                            <li>
                                <a href="?remove_attachment={$attachment->getFileName()}" class="rm_attachement">
                                    <img alt="{_T string="Remove attachment"}" src="./templates/default/images/delete.png">
                                </a>
                                {$attachment->getFileName()}
                            </li>
                            {/foreach}
                        </ul>
                    </p>
                    {/if}
                    <label for="attachment" class="bline tooltip" title="{_T string="Select attachments"}">{_T string="Add attachment"}</label>
                    <span class="tip">{_T string="Select files to add as attachments.<br/>Multiple file selection using 'ctrl' or 'shift' keys are only available on compatible browsers."}</span>
                    <input type="file" name="files[]" name="attachment" id="attachment" multiple="multiple">
                </div>
            </section>

            <section class="mailing_write">
                <header class="ui-state-default ui-state-active">{_T string="Write your mailing"}</header>
                <div>
                    <label for="mailing_objet" class="bline">{_T string="Object:"}</label>
                    <input type="text" name="mailing_objet" id="mailing_objet" value="{$mailing->subject}" size="80" required/>
                </div>
                <div>
                    <span class="fright"><a href="javascript:toggleMailingEditor('mailing_corps');" id="toggle_editor">{_T string="(De)Activate HTML editor"}</a></span>
                    <label for="mailing_corps" class="bline">{_T string="Message:"}</label>
                    <textarea name="mailing_corps" id="mailing_corps" cols="80" rows="15" required>{$mailing->message|escape}</textarea>
                    <input type="hidden" name="html_editor_active" id="html_editor_active" value="{if $html_editor_active}1{else}0{/if}"/>
                </div>
                <div class="center">
                    <input type="checkbox" name="mailing_html" id="mailing_html" value="1" {if $mailing->html eq 1 or $pref_editor_enabled eq 1}checked="checked"{/if}/><label for="mailing_html">{_T string="Interpret HTML"}</label><br/>
                    <input type="submit" id="btnpreview" name="mailing_go" value="{_T string="Preview"}"/>
                    <input type="submit" id="btnsave" name="mailing_save" value="{_T string="Save"}"/>
                    <input type="submit" id="btnsend" name="mailing_confirm" value="{_T string="Send"}"{if $GALETTE_MODE eq 'DEMO'} class="disabled" disabled="disabled"{/if}/>
                    <input type="submit" id="btncancel" name="mailing_cancel" value="{_T string="Cancel mailing"}" formnovalidate/>
                </div>
            </section>
        {/if}
        {if $mailing->current_step eq constant('Galette\Core\Mailing::STEP_PREVIEW')}
            <section class="mailing_write" id="mail_preview">
                <header class="ui-state-default ui-state-active">{_T string="Preview your mailing"}</header>
                <div>
                    <p><span class="bline">{_T string="Object:"}</span>{$mailing->subject}</p>
                    <p>
                        <span class="bline">{_T string="Message:"}</span><br/>
            {if $mailing->html}
                    {$mailing->message}
            {else}
                        <pre>{$mailing->message}</pre>
            {/if}
                    </p>
                </div>
                <div>
                    <p>
                        <input type="submit" name="mailing_reset" class="button" id="btnback" value="{_T string="Modifiy mailing"}"/>
                        <input type="submit" name="mailing_confirm" value="{_T string="Send"}"{if $GALETTE_MODE eq 'DEMO'} class="disabled" disabled="disabled"{/if}/>
                        <input type="submit" id="btncancel" name="mailing_cancel" value="{_T string="Cancel mailing"}"/>
                        <input type="hidden" name="mailing_objet" value="{$mailing->subject}"/>
                        <input type="hidden" name="mailing_corps" value="{$mailing->message|escape}"/>
                    </p>
                </div>
        {/if}

            </section>
        </div>
        </form>
    {if $mailing->current_step neq constant('Galette\Core\Mailing::STEP_SENT')}
<script type="text/javascript">
    $(function() {
        {* Preview popup *}
        $('#btnpreview').click(function(){
            var _subject = $('#mailing_objet').val();
            var _body = $('#mailing_corps').val();
            var _html = $('#mailing_html').is(':checked');
            var _attachments = [];
            $('#existing_attachments li').each(function(){
                _attachments[_attachments.length] = $(this).text();
            });
            $.ajax({
                url: 'ajax_mailing_preview.php',
                type: "POST",
                data: {
                    ajax: true,
                    subject: _subject,
                    body: _body,
                    html: _html,
                    attachments: _attachments
                },
                {include file="js_loader.tpl"},
                success: function(res){
                    _preview_dialog(res);
                },
                error: function() {
                    alert("{_T string="An error occured displaying preview :(" escape="js"}");
                }
            });
            return false;
        });

        var _preview_dialog = function(res){
            var _el = $('<div id="ajax_preview" title="{_T string="Mailing preview" escape="js"}"> </div>');
            _el.appendTo('body').dialog({
                modal: true,
                hide: 'fold',
                width: '80%',
                height: 500,
                close: function(event, ui){
                    _el.remove();
                }
            });
            $('#ajax_preview').append( res );
        }

        {* Members popup *}
        $('#btnusers').click(function(){
            $.ajax({
                url: 'ajax_members.php',
                type: "POST",
                data: {
                    ajax: true
                },
                {include file="js_loader.tpl"},
                success: function(res){
                    _members_dialog(res);
                },
                error: function() {
                    alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                }
            });
            return false;
        });

        var _members_dialog = function(res){
            var _el = $('<div id="members_list" title="{_T string="Members selection" escape="js"}"> </div>');
            _el.appendTo('body').dialog({
                modal: true,
                hide: 'fold',
                width: '80%',
                height: 500,
                close: function(event, ui){
                    _el.remove();
                }
            });
            _members_ajax_mapper(res);
        }

        var _members_ajax_mapper = function(res){
            $('#members_list').append(res);
            $('#selected_members ul').css(
                'max-height',
                $('#members_list').innerHeight() - $('#btnvalid').outerHeight() - $('#selected_members header').outerHeight() - 60 // -60 to fix display; do not know why
            );
            $('#btnvalid').button().click(function(){
                //first, let's store new recipients in mailing object
                var _recipients = new Array();
                $('li[id^="member_"]').each(function(){
                    _recipients[_recipients.length] = this.id.substring(7, this.id.length);
                });
                $.ajax({
                    url: 'ajax_recipients.php',
                    type: "POST",
                    data: {
                        recipients: _recipients
                    },
                    {include file="js_loader.tpl"},
                    success: function(res){
                        $('#unreachables_count').remove();
                        $('#recipients_count').replaceWith(res);
                        $('.mailing_infos input:submit, .mailing_infos .button, .mailing_infos input:reset' ).button();
                        $('#members_list').dialog("close");
                    },
                    error: function() {
                        alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                    }
                });
            });
            //Remap links
            var _none = $('#none_selected').clone();
            $('li[id^="member_"]').click(function(){
                $(this).remove();
                if ( $('#selected_members ul li').length == 0 ) {
                    $('#selected_members ul').append(_none);
                }
            });
            $('#listing tbody a').click(function(){
                var _mid = this.href.substring(this.href.indexOf('?')+8);
                var _mname = $(this).text();
                $('#none_selected').remove()
                if ( $('#member_' + _mid).length == 0 ) {
                    var _li = '<li id="member_' + _mid + '">' + _mname + '</li>';
                    $('#selected_members ul').append(_li);
                    $('#member_' + _mid).click(function(){
                        $(this).remove();
                        if ( $('#selected_members ul li').length == 0 ) {
                            $('#selected_members ul').append(_none);
                        }
                    });
                }
                return false;
            });

            $('#members_list .pages a').click(function(){
                var _page = this.href.substring(this.href.indexOf('?')+6);
                var _members = new Array();
                $('li[id^="member_"]').each(function(){
                    _members[_members.length] = this.id.substring(7, this.id.length);
                });

                $.ajax({
                    url: 'ajax_members.php',
                    type: "POST",
                    data: {
                        ajax: true,
                        members: _members,
                        page: _page
                    },
                    {include file="js_loader.tpl"},
                    success: function(res){
                        $('#members_list').empty();
                        _members_ajax_mapper(res);
                    },
                    error: function() {
                        alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                    }
                });
                return false;
            });
        }

        $('.rm_attachement').click(function(){
            var _link = $(this);
            var _el = $('<div title="{_T string="Remove attachment"}"><p>{_T string="Are you sure you want to remove this attachment?"}</p><p>{_T string="This will immediately remove attachment from disk and cannot be undo."}</p></div>');
            _el.appendTo('body').dialog({
                modal: true,
                hide: 'fold',
                buttons: {
                    Ok: function() {
                        var _this = $(this);
                        _this.dialog( "close" );
                        window.location.href = 'mailing_adherents.php' + _link.attr('href');
                    },
                    {_T string="Cancel"}: function() {
                         $(this).dialog( "close" );
                    }
                },
                close: function(event, ui){
                    _el.remove();
                }
            });
            return false;
        });
    });
</script>
    {/if}
{/if}
