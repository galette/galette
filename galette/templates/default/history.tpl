        <table class="listing">
            <thead>
                <tr>
                    <td colspan="3">
                        <a id="histreset" class="button" href="history.php?reset=1">{_T string="Flush the logs"}</a>
                    </td>
                    <td colspan="3" class="right">
                        <form action="history.php" method="get" id="historyform">
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
                        <a href="history.php?tri=date_log">
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
                    <th class="left date_row">
                        <a href="history.php?tri=ip_log">
                            {_T string="IP"}
                            {if $history->orderby eq "ip_log"}
                                {if $history->getDirection() eq "DESC"}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt="{_T string="Ascendent"}"/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt="{_T string="Descendant"}"/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="left date_row">
                        <a href="history.php?tri=adh_log">
                            {_T string="User"}
                            {if $history->orderby eq "adh_log"}
                                {if $history->getDirection() eq "DESC"}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt="{_T string="Ascendent"}"/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt="{_T string="Descendant"}"/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="left username_row">
                        <a href="history.php?tri=action_log">
                            {_T string="Action"}
                            {if $history->orderby eq "action_log"}
                                {if $history->getDirection() eq "DESC"}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt="{_T string="Ascendent"}"/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt="{_T string="Descendant"}"/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                    <th class="left">
                        <a href="history.php?tri=text_log">
                            {_T string="Description"}
                            {if $history->orderby eq "text_log"}
                                {if $history->getDirection() eq "DESC"}
                            <img src="{$template_subdir}images/down.png" width="10" height="6" alt="{_T string="Ascendent"}"/>
                                {else}
                            <img src="{$template_subdir}images/up.png" width="10" height="6" alt="{_T string="Descendant"}"/>
                                {/if}
                            {/if}
                        </a>
                    </th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <td colspan="6" class="center">
                        {_T string="Pages:"}<br/>
                        <ul class="pages">{$pagination}</ul>
                    </td>
                </tr>
            </tfoot>
            <tbody>
{foreach from=$logs item=log name=eachlog}
                <tr class="{if $smarty.foreach.eachlog.iteration % 2 eq 0}even{else}odd{/if}">
                    <td class="center">{$smarty.foreach.eachlog.iteration}</td>
                    <td class="nowrap">{$log.date_log|date_format:"%a %d/%m/%Y - %R"}</td>
                    <td class="nowrap">{$log.ip_log}</td>
                    <td>{$log.adh_log}</td>
                    <td>{$log.action_log}</td>
                    <td>
                        {$log.text_log}
    {if $log.sql_log}
                        <span class="sql_log">{$log.sql_log|escape:"htmlall"}</span>
    {/if}
                    </td>
                </tr>
{foreachelse}
                <tr><td colspan="6" class="emptylist">{_T string="logs are empty"}</td></tr>
{/foreach}
            </tbody>
        </table>
        <script type="text/javascript">
            $('#nbshow').change(function() {
                this.form.submit();
            });

            $(function() {
                var _elt = $('<img src="templates/default/images/info.png" class="qryhide" alt="" title="{_T string="Show associated query"}"/>');
                $('.sql_log').hide().parent().prepend(_elt);
                $('.qryhide').click(function() {
                    $(this).next('.sql_log').show();
                });
            });
        </script>
