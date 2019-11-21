{extends file="page.tpl"}
{block name="content"}
{if $pref_mail_method == constant('Galette\Core\Mailing::METHOD_DISABLED') and $GALETTE_MODE neq 'DEMO'}
        <div id="errorbox">
            <h1>{_T string="- ERROR -"}</h1>
            <p>{_T string="Email sent is disabled in the preferences. Ask galette admin"}</p>
        </div>
{elseif !isset($mailing_saved)}
        <form action="{path_for name="doMailing"}" id="listform" method="post" enctype="multipart/form-data">
        <div class="mailing">
            <section class="mailing_infos">
                <header class="ui-state-default ui-state-active">{_T string="Mailing informations"}</header>
                    {include file="mailing_recipients.tpl"}
                <div class="center">
    {if $mailing->current_step eq constant('Galette\Core\Mailing::STEP_SENT')}
        {assign var="path" value={path_for name="members"}}
        {assign var="text" value={_T string="Go back to members list"}}
                    <a
                        id="btnusers"
                        href="{$path}"
                        class="button"
                    >
                        <i class="fas fa-users"></i>
                        {$text}
                    </a>
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
                                    <img alt="{_T string="Remove attachment"}" src="{base_url}/{$template_subdir}images/delete.png">
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
                    <label for="sender" class="bline">{_T string="Sender"}</label>
                    <select name="sender" id="sender">
                        <option value="{Galette\Core\GaletteMail::SENDER_PREFS}">{_T string="from preferences"}</option>
    {if !$login->isSuperAdmin()}
                        <option value="{Galette\Core\GaletteMail::SENDER_CURRENT}">{_T string="current logged in user"}</option>
    {/if}
                        <option value="{Galette\Core\GaletteMail::SENDER_OTHER}">{_T string="other"}</option>
                    </select>
                    <span class="disabled">
                        <label for="sender_name">{_T string="Name"}</label>
                        <input type="text" name="sender_name" id="sender_name" value="{$preferences->pref_email_nom}" disabled="disabled"/>
                        <label for="sender_address">{_T string="Address"}</label>
                        <input type="text" name="sender_address" id="sender_address" value="{$preferences->pref_email}" disabled="disabled"/>
                    </span>
                </div>
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
                    <button type="submit" name="mailing_go" id="btnpreview">
                        <i class="fas fa-eye" arai-hidden="true"></i>
                        {_T string="Preview"}
                    </button>
                    <button type="submit" name="mailing_save" class="action">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        {_T string="Save"}
                    </button>
                    <button type="submit" name="mailing_confirm"{if $GALETTE_MODE eq 'DEMO'} class="disabled" disabled="disabled"{/if}>
                        <i class="fas fa-rocket" aria-hidden="true"></i>
                        {_T string="Send"}
                    </button>
                    <button type="submit" name="mailing_cancel" formnovalidate>
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        {_T string="Cancel mailing"}
                    </button>
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
                        <pre>{$mailing->wrapped_message}</pre>
            {/if}
                    </p>
                </div>
                <div>
                    <p>
                        <input type="submit" name="mailing_reset" class="button" id="btnback" value="{_T string="Modifiy mailing"}"/>
                        <input type="submit" name="mailing_confirm" id="btnsend" value="{_T string="Send"}"{if $GALETTE_MODE eq 'DEMO'} class="disabled" disabled="disabled"{/if}/>
                        <input type="submit" id="btncancel" name="mailing_cancel" value="{_T string="Cancel mailing"}"/>
                        <input type="hidden" name="mailing_objet" value="{$mailing->subject}"/>
                        <input type="hidden" name="mailing_corps" value="{$mailing->message|escape}"/>
                    </p>
                </div>
        {/if}

            </section>
        </div>
        </form>
{/if}
{/block}

{block name="javascripts"}
{if ($pref_mail_method != constant('Galette\Core\Mailing::METHOD_DISABLED') or $GALETTE_MODE eq 'DEMO') and !isset($mailing_saved)}
    {if $mailing->current_step neq constant('Galette\Core\Mailing::STEP_SENT')}
<script type="text/javascript">
    $(function() {
        {* Preview popup *}
        $('#btnpreview').click(function(){
            var _sender = $('#sender').val();
            var _sender_name = $('#sender_name').val();
            var _sender_address = $('#sender_address').val();
            var _subject = $('#mailing_objet').val();
            var _body = $('#mailing_corps').val();
            var _html = $('#mailing_html').is(':checked');
            var _attachments = [];
            $('#existing_attachments li').each(function(){
                _attachments[_attachments.length] = $(this).text();
            });
            $.ajax({
                url: '{path_for name="mailingPreview"}',
                type: "POST",
                data: {
                    sender: _sender,
                    sender_name: _sender_name,
                    sender_address: _sender_address,
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
                    alert("{_T string="An error occurred displaying preview :(" escape="js"}");
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
                        window.location.href = '{path_for name="mailing"}' + _link.attr('href');
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

        $('#sender').on('change', function() {
            var _this = $(this);
            var _sender_name = $('#sender_name');
            var _sender_address = $('#sender_address');
            var _editable = false;
            var _val = _this.val();
            switch (_val) {
                case '{Galette\Core\GaletteMail::SENDER_PREFS}':
                    _sender_name.val('{$preferences->pref_email_nom|escape:"javascript"}');
                    _sender_address.val('{$preferences->pref_email|escape:"javascript"}');
                    break;
                case '{Galette\Core\GaletteMail::SENDER_CURRENT}':
                    _sender_name.val('{$sender_current['name']|escape:"javascript"}');
                    _sender_address.val('{$sender_current['email']|escape:"javascript"}');
                    break;
                case '{Galette\Core\GaletteMail::SENDER_OTHER}':
                    _sender_name.val('');
                    _sender_address.val('');
                    _editable = true;
                    break;
            }

            if (_editable) {
                _sender_name.removeAttr('disabled');
                _sender_address.removeAttr('disabled');
                $('#sender + span').removeClass('disabled');
            } else {
                _sender_name.attr('disabled', 'disabled');
                _sender_address.attr('disabled', 'disabled');
                $('#sender + span').addClass('disabled');
            }
        });
    });
</script>
    {/if}
{/if}
{/block}
