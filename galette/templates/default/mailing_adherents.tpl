{if $pref_mail_method == constant('Mailing::METHOD_DISABLED')}
		<div id="errorbox">
			<h1>{_T string="- ERROR -"}</h1>
			<p>{_T string="Email sent is disabled in the preferences. Ask galette admin"}</p>
		</div>
{elseif !$mailing_saved}
		<form action="mailing_adherents.php#mail_preview" id="listform" method="post">
        <div class="mailing">
            <section class="mailing_infos">
                <header class="ui-state-default ui-state-active">{_T string="Mailing informations"}</header>
    {assign var='count' value=$mailing->recipients|@count}
    {assign var='count_unreachables' value=$mailing->unreachables|@count}
    {if $count > 0}
        {if $mailing->current_step eq constant('Mailing::STEP_SENT')}
                <p>{_T string="Your message has been sent to <strong>%s members</strong>" pattern="/%s/" replace=$count}</p>
        {else}
                <p id="recipients_count">{_T string="You are about to send an e-mail to <strong>%s members</strong>" pattern="/%s/" replace=$count}</p>
        {/if}
        {if $count_unreachables > 0}
                <p id="unreachables_count">
                    <strong>{$count_unreachables} {if $count_unreachables != 1}{_T string="unreachable members:"}{else}{_T string="unreachable member:"}{/if}</strong><br/>
                    {_T string="Some members you have selected have no e-mail address. However, you can generate envelope labels to contact them by snail mail."}
                    <br/><a id="btnlabels" class="button" href="etiquettes_adherents.php?from=mailing">{_T string="Generate labels"}</a>
                </p>
        {/if}
    {else}
        {if $count_unreachables > 0}
                <p id="recipients_count"><strong>{_T string="None of the selected members has an email address."}</strong></p>
         {else}
                <p id="recipients_count"><strong>{_T string="No member selected (yet)."}</strong></p>
         {/if}
    {/if}
                <div class="center">
    {if $mailing->current_step eq constant('Mailing::STEP_SENT')}
                    <a class="button" id="btnusers" href="gestion_adherents.php">{_T string="Go back to members list"}</a>
    {else}
                    <a class="button" id="btnusers" href="gestion_adherents.php?nbshow=0&showChecked=true">{_T string="Manage selected members"}</a>
    {/if}
                </div>
            </section>
        {if $mailing->current_step eq constant('Mailing::STEP_START')}
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
                    <input type="submit" id="btnsend" name="mailing_confirm" value="{_T string="Send"}"/>
                    <input type="submit" id="btncancel" name="mailing_cancel" value="{_T string="Cancel mailing"}" formnovalidate/>
                </div>
            </section>
        {/if}
        {if $mailing->current_step eq constant('Mailing::STEP_PREVIEW')}
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
                        <input type="submit" name="mailing_confirm" value="{_T string="Send"}"/>
                        <input type="submit" id="btncancel" name="mailing_cancel" value="{_T string="Cancel mailing"}"/>
                        <input type="hidden" name="mailing_objet" value="{$mailing->subject}"/>
                        <input type="hidden" name="mailing_corps" value="{$mailing->message|escape}"/>
                    </p>
                </div>
        {/if}

            </section>
        </div>
		</form>
    {if $mailing->current_step neq constant('Mailing::STEP_SENT')}
<script type="text/javascript">
    $(function() {ldelim}
        {* Preview popup *}
        $('#btnpreview').click(function(){ldelim}
            var _subject = $('#mailing_objet').val();
            var _body = $('#mailing_corps').val();
            var _html = $('#mailing_html').is(':checked');
            $.ajax({ldelim}
                url: 'ajax_mailing_preview.php',
                type: "POST",
                data: {ldelim}ajax: true, subject: _subject, body: _body, html: _html{rdelim},
                {include file="js_loader.tpl"},
                success: function(res){ldelim}
                    _preview_dialog(res);
                {rdelim},
                error: function() {ldelim}
                    alert("{_T string="An error occured displaying preview :(" escape="js"}");
                {rdelim}
            });
            return false;
        {rdelim});

        var _preview_dialog = function(res){ldelim}
            var _el = $('<div id="ajax_preview" title="{_T string="Mailing preview" escape="js"}"> </div>');
            _el.appendTo('body').dialog({ldelim}
                modal: true,
                hide: 'fold',
                width: '80%',
                height: 500,
                close: function(event, ui){ldelim}
                    _el.remove();
                {rdelim}
            {rdelim});
            $('#ajax_preview').append( res );
        {rdelim}

        {* Members popup *}
        $('#btnusers').click(function(){ldelim}
            $.ajax({ldelim}
                url: 'ajax_members.php',
                type: "POST",
                data: {ldelim}ajax: true{rdelim},
                {include file="js_loader.tpl"},
                success: function(res){ldelim}
                    _members_dialog(res);
                {rdelim},
                error: function() {ldelim}
                    alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                {rdelim}
            });
            return false;
        {rdelim});

        var _members_dialog = function(res){ldelim}
            var _el = $('<div id="members_list" title="{_T string="Members selection" escape="js"}"> </div>');
            _el.appendTo('body').dialog({ldelim}
                modal: true,
                hide: 'fold',
                width: '80%',
                height: 500,
                close: function(event, ui){ldelim}
                    _el.remove();
                {rdelim}
            {rdelim});
            _members_ajax_mapper(res);

        {rdelim}

        var _members_ajax_mapper = function(res){ldelim}
            $('#members_list').append(res);
            $('#selected_members ul').css(
                'max-height',
                $('#members_list').innerHeight() - $('#btnvalid').outerHeight() - $('#selected_members header').outerHeight() - 60 // -60 to fix display; do not know why
            );
            $('#btnvalid').button().click(function(){ldelim}
                //first, let's store new recipients in mailing object
                var _recipients = new Array();
                $('li[id^="member_"]').each(function(){ldelim}
                    _recipients[_recipients.length] = this.id.substring(7, this.id.length);
                {rdelim});
                $.ajax({ldelim}
                    url: 'ajax_recipients.php',
                    type: "POST",
                    data: {ldelim}recipients: _recipients{rdelim},
                    {include file="js_loader.tpl"},
                    success: function(res){ldelim}
                        $('#unreachables_count').remove();
                        $('#recipients_count').replaceWith(res);
                        $('.mailing_infos input:submit, .mailing_infos .button, .mailing_infos input:reset' ).button();
                        $('#members_list').dialog("close");
                    {rdelim},
                    error: function() {ldelim}
                        alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                    {rdelim}
                });
            {rdelim});
            //Remap links
            var _none = $('#none_selected').clone();
            $('li[id^="member_"]').click(function(){ldelim}
                $(this).remove();
                if ( $('#selected_members ul li').length == 0 ) {ldelim}
                    $('#selected_members ul').append(_none);
                {rdelim}
            {rdelim});
            $('#listing tbody a').click(function(){ldelim}
                var _mid = this.href.substring(this.href.indexOf('?')+8);
                var _mname = $(this).text();
                $('#none_selected').remove()
                if ( $('#member_' + _mid).length == 0 ) {ldelim}
                    var _li = '<li id="member_' + _mid + '">' + _mname + '</li>';
                    $('#selected_members ul').append(_li);
                    $('#member_' + _mid).click(function(){ldelim}
                        $(this).remove();
                        if ( $('#selected_members ul li').length == 0 ) {ldelim}
                            $('#selected_members ul').append(_none);
                        {rdelim}
                    {rdelim});
                {rdelim}
                return false;
            {rdelim});

            $('#members_list .pages a').click(function(){ldelim}
                var _page = this.href.substring(this.href.indexOf('?')+6);
                var _members = new Array();
                $('li[id^="member_"]').each(function(){ldelim}
                    _members[_members.length] = this.id.substring(7, this.id.length);
                {rdelim});

                $.ajax({ldelim}
                    url: 'ajax_members.php',
                    type: "POST",
                    data: {ldelim}ajax: true, members: _members, page: _page{rdelim},
                    {include file="js_loader.tpl"},
                    success: function(res){ldelim}
                        $('#members_list').empty();
                        _members_ajax_mapper(res);
                    {rdelim},
                    error: function() {ldelim}
                        alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                    {rdelim}
                });
                return false;
            {rdelim});
        {rdelim}
    {rdelim});
</script>
    {/if}
{/if}
