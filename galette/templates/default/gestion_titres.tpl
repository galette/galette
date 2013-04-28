        <form action="gestion_titres.php" method="post" enctype="multipart/form-data">
                <table class="listing">
                    <thead>
                        <tr>
                            <th class="id_row">#</th>
                            <th>{_T string="Short form"}</th>
                            <th>{_T string="Long form"}</th>
                            <th>{_T string="Actions"}</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <td>&nbsp;</td>
                            <td class="left">
                                <input size="20" type="text" name="short_label"/>
                            </td>
                            <td class="left">
                                <input size="20" type="text" name="long_label"/>
                            </td>
                            <td class="center">
                                <input type="hidden" name="new" value="1" />
                                <input type="submit" name="valid" id="btnadd" value="{_T string="Add"}"/>
                            </td>
                        </tr>
                    </tfoot>
                    <tbody>
            {foreach from=$titles_list item=title name=alltitles}
                        <tr class="{if $smarty.foreach.alltitles.iteration % 2 eq 0}even{else}odd{/if}">
                            <td>{$title->id}</td>
                            <td class="left">{$title->short}</td>
                            <td class="left">{$title->long}</td>
                            <td class="center actions_row">

                                <a href="edit_title.php?id={$title->id}">
                                    <img src="{$template_subdir}images/icon-edit.png" alt="{_T string="Edit '%s' title" pattern="/%s/" replace=$title->short}" title="{_T string="Edit '%s' title" pattern="/%s/" replace=$title->short}" width="16" height="16"/>
                                </a>
                {if $title->id eq 1 or $title->id eq 2}
                                <img src="{$template_subdir}images/icon-empty.png" alt="" width="16px" height="16px"/>
                {else}
                                <a onclick="return confirm('{_T string="Do you really want to delete this entry?"|escape:"javascript"}')" href="gestion_titres.php?del={$title->id}">
                                    <img src="{$template_subdir}images/icon-trash.png" alt="{_T string="Delete '%s' title" pattern="/%s/" replace=$title->short}" title="{_T string="Delete '%s' title" pattern="/%s/" replace=$title->short}" width="16" height="16" />
                                </a>
                {/if}
                            </td>
                        </tr>
            {/foreach}
                    </tbody>
                </table>
        </form>
