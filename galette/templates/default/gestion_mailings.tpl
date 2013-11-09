        <table class="listing">
            <thead>
                <tr>
                    <td colspan="7" class="right">
                        <form action="gestion_mailings.php" method="get" id="historyform">
                            <span>
                                <label for="nbshow">{_T string="Records per page:"}</label>
                                <select name="nbshow" id="nbshow">
                                    {html_options options=$nbshow_options selected=$numrows}
                                </select>
                                <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
                            </span>
                        </form>
                    </td>
                </tr>
                <tr>
                    <th class="small_head">#</th>
                    <th class="left date_row">
                        <a href="?tri=mailing_date">
                            {_T string="Date"}
                            {if $history->orderby eq "date_log"}
                                {if $history->getDirection() eq "DESC"}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt="{_T string="Ascendent"}"/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt="{_T string="Descendant"}"/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="left username_row">
                        <a href="?tri=mailing_sender">
                            {_T string="Sender"}
                            {if $history->orderby eq "adh_log"}
                                {if $history->getDirection() eq "DESC"}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt="{_T string="Ascendent"}"/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt="{_T string="Descendant"}"/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="left small_head">
                        {_T string="Recipients"}
                    </th>
                    <th class="left">
                        <a href="?tri=mailing_subject">
                            {_T string="Subject"}
                            {if $history->orderby eq "action_log"}
                                {if $history->getDirection() eq "DESC"}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt="{_T string="Ascendent"}"/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt="{_T string="Descendant"}"/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th title="{_T string="Attachments"}" class="small_head">
                        {_T string="Att."}
                    </th>
                    <th class="left right small_head">
                        <a href="?tri=mailing_sent">
                            {_T string="Sent"}
                            {if $history->orderby eq "sent"}
                                {if $history->getDirection() eq "DESC"}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt="{_T string="Ascendent"}"/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt="{_T string="Descendant"}"/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="small_head"></th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <td colspan="7" class="center">
                        {_T string="Pages:"}<br/>
                        <ul class="pages">{$pagination}</ul>
                    </td>
                </tr>
            </tfoot>
            <tbody>
{foreach from=$logs item=log name=eachlog}
                <tr class="{if $smarty.foreach.eachlog.iteration % 2 eq 0}even{else}odd{/if}">
                    <td class="center">{$smarty.foreach.eachlog.iteration}</td>
                    <td class="nowrap">{$log.mailing_date|date_format:"%a %d/%m/%Y - %R"}</td>
                    <td>{if $log.mailing_sender eq 0}{_T string="Superadmin"}{else}{$log.mailing_sender_name}{/if}</td>
                    <td>{$log.mailing_recipients|unserialize|@count}</td>
                    <td>{$log.mailing_subject}</td>
                    <td>{$log.attachments}</td>
                    <td class="center">
                        {if $log.mailing_sent == 1}
                            <img src="{$template_subdir}images/icon-on.png" alt="{_T string="Sent"}" title="{_T string="Mailing has been sent"}"/>
                        {else}
                            <img src="{$template_subdir}images/icon-off.png" alt="{_T string="Not sent"}" title="{_T string="Mailing has not been sent yet"}"/>
                        {/if}
                    </td>
                    <td class="center nowrap actions_row">
                        <a class="showdetails" href="ajax_mailing_preview.php?id={$log.mailing_id}">
                            <img
                                src="{$template_subdir}images/icon-preview.png"
                                alt="{_T string="Show mailing %s details" pattern="/%s/" replace=$log.mailing_id}"
                                width="16"
                                height="16"
                                title="{_T string="Display mailing '%subject' details in preview window" pattern="/%subject/" replace=$log.mailing_subject}"
                                />
                        </a>
                        <a href="mailing_adherents.php?from={$log.mailing_id}">
                            <img
                                src="{$template_subdir}images/icon-mail.png"
                                alt="{_T string="New mailing from %s" pattern="/%s/" replace=$log.mailing_id}"
                                width="16"
                                height="16"
                                title="{_T string="Use mailing '%subject' as a template for a new one" pattern="/%subject/" replace=$log.mailing_subject}"
                                />
                        </a>
                        <a
                            onclick="return confirm('{_T string="Do you really want to delete this mailing from the base?"|escape:"javascript"}')"
                            title="{_T string="Delete mailing '%subject'" pattern="/%subject/" replace=$log.mailing_subject}"
                            href="?sup={$log.mailing_id}">
                            <img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16"/>
                        </a>
                    </td>
                </tr>
{foreachelse}
                <tr><td colspan="8" class="emptylist">{_T string="No sent mailing has been stored in the database yet."}</td></tr>
{/foreach}
            </tbody>
        </table>
        <div class="center">
            <a class="button" id="btnadd" href="mailing_adherents.php?mailing_new=true">{_T string="Create new mailing"}</a>
        </div>
        <script type="text/javascript">
            $('#nbshow').change(function() {
                this.form.submit();
            });

            {* Preview popup *}
            $('.showdetails').click(function(){
                $.ajax({
                    url: $(this).attr('href'),
                    type: "POST",
                    data: {
                        ajax: true,
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

        </script>
