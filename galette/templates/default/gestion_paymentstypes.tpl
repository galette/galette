{extends file="page.tpl"}

{block name="content"}
        <form action="{path_for name="paymentTypes"}" method="post" enctype="multipart/form-data">
                <table class="listing">
                    <thead>
                        <tr>
                            <th class="id_row">#</th>
                            <th>{_T string="Name"}</th>
                            <th>{_T string="Actions"}</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <td data-scope="row">
                                <span class="row-title">
                                    {_T string="Add payment type"}
                                </span>
                            </td>
                            <td class="left" data-title="{_T string="Label"}">
                                <input size="20" type="text" name="name"/>
                            </td>
                            <td class="center actions_row">
                                <input type="hidden" name="new" value="1" />
                                <input type="submit" name="valid" id="btnadd" value="{_T string="Add"}"/>
                            </td>
                        </tr>
                    </tfoot>
                    <tbody>
            {foreach from=$list item=ptype name=alltypes}
                        <tr class="{if $smarty.foreach.alltypes.iteration % 2 eq 0}even{else}odd{/if}">
                            <td data-scope="row">
                                {$ptype->id}
                                <span class="row-title">
                                    <a href="{path_for name="editPaymentType" data=["id" => $ptype->id]}">
                                        {_T string="%s payment type" pattern="/%s/" replace=$ptype->getName()}
                                    </a>
                                </span>
                            </td>
                            <td class="left" data-title="{_T string="Name"}">{$ptype->getName()}</td>
                            <td class="center actions_row">
                                <a href="{path_for name="editPaymentType" data=["id" => $ptype->id]}">
                                    <img src="{base_url}/{$template_subdir}images/icon-edit.png" alt="{_T string="Edit '%s' payment type" pattern="/%s/" replace=$ptype->getName()}" title="{_T string="Edit '%s' payment type" pattern="/%s/" replace=$ptype->getName()}" width="16" height="16"/>
                                <a href="{path_for name="dynamicTranslations" data=["text_orig" => {$ptype->getName(false)|escape}]}"><img src="{base_url}/{$template_subdir}images/icon-i18n.png" alt="{_T string="Translate '%s'" pattern="/%s/" replace=$ptype->getName()}" title="{_T string="Translate '%s'" pattern="/%s/" replace=$ptype->getName()}" width="16" height="16"/></a>
                                </a>
                {if $ptype->isSystemType()}
                                <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16px" height="16px"/>
                {else}
                                <a class="delete" href="{path_for name="removePaymentType" data=["id" => $ptype->id]}">
                                    <img src="{base_url}/{$template_subdir}images/icon-trash.png" alt="{_T string="Delete '%s' payment type" pattern="/%s/" replace=$ptype->getName()}" title="{_T string="Delete '%s' payment type" pattern="/%s/" replace=$ptype->getName()}" width="16" height="16">
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
