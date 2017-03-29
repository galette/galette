{extends file="page.tpl"}

{block name="content"}
        <form action="{path_for name="titles"}" method="post" enctype="multipart/form-data">
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
                            <td data-scope="row">
                                <span class="row-title">
                                    {_T string="Add title"}
                                </span>
                            </td>
                            <td class="left" data-title="{_T string="Short form"}">
                                <input size="20" type="text" name="short_label"/>
                            </td>
                            <td class="left" data-title="{_T string="Long form"}">
                                <input size="20" type="text" name="long_label"/>
                            </td>
                            <td class="center actions_row">
                                <input type="hidden" name="new" value="1" />
                                <input type="submit" name="valid" id="btnadd" value="{_T string="Add"}"/>
                            </td>
                        </tr>
                    </tfoot>
                    <tbody>
            {foreach from=$titles_list item=title name=alltitles}
                        <tr class="{if $smarty.foreach.alltitles.iteration % 2 eq 0}even{else}odd{/if}">
                            <td data-scope="row">
                                {$title->id}
                                <span class="row-title">
                                    <a href="{path_for name="editTitle" data=["id" => $title->id]}">
                                        {_T string="%s title" pattern="/%s/" replace=$title->short}
                                    </a>
                                </span>
                            </td>
                            <td class="left" data-title="{_T string="Short form"}">{$title->short}</td>
                            <td class="left" data-title="{_T string="Long form"}">{$title->long}</td>
                            <td class="center actions_row">

                                <a href="{path_for name="editTitle" data=["id" => $title->id]}">
                                    <img src="{$template_subdir}images/icon-edit.png" alt="{_T string="Edit '%s' title" pattern="/%s/" replace=$title->short}" title="{_T string="Edit '%s' title" pattern="/%s/" replace=$title->short}" width="16" height="16"/>
                                </a>
                {if $title->id eq 1 or $title->id eq 2}
                                <img src="{$template_subdir}images/icon-empty.png" alt="" width="16px" height="16px"/>
                {else}
                                <a class="delete" href="{path_for name="removeTitle" data=["id" => $title->id]}">
                                    <img src="{$template_subdir}images/icon-trash.png" alt="{_T string="Delete '%s' title" pattern="/%s/" replace=$title->short}" title="{_T string="Delete '%s' title" pattern="/%s/" replace=$title->short}" width="16" height="16" />
                                </a>
                {/if}
                            </td>
                        </tr>
            {/foreach}
                    </tbody>
                </table>
        </form>
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            $(function() {
                {include file="js_removal.tpl"}
            });
        </script>
{/block}
