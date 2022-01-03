{extends file="page.tpl"}

{block name="content"}
        <form action="{path_for name="paymentTypes"}" method="post" enctype="multipart/form-data" class="ui form">
                <table class="listing ui celled table">
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
                                <button type="submit" name="valid" class="ui labeled icon button">
                                    <i class="plus green icon" aria-disabled="true"></i>
                                    {_T string="Add"}
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                    <tbody>
            {foreach from=$list item=ptype name=alltypes}
                        <tr class="{if $smarty.foreach.alltypes.iteration % 2 eq 0}even{else}odd{/if}">
                            <td data-scope="row">
    {if $preferences->pref_show_id}
                                {$ptype->id}
    {else}
                                {$smarty.foreach.alltypes.iteration}
    {/if}
                                <span class="row-title">
                                    <a href="{path_for name="editPaymentType" data=["id" => $ptype->id]}">
                                        {_T string="%s payment type" pattern="/%s/" replace=$ptype->getName()}
                                    </a>
                                </span>
                            </td>
                            <td class="left" data-title="{_T string="Name"}">{$ptype->getName()}</td>
                            <td class="center actions_row">
                                <a
                                    href="{path_for name="editPaymentType" data=["id" => $ptype->id]}"
                                    class="tooltip action"
                                >
                                    <i class="ui edit icon"></i>
                                    <span class="sr-only">{_T string="Edit '%s' payment type" pattern="/%s/" replace=$ptype->getName()}</span>
                                </a>
                                <a
                                    href="{path_for name="dynamicTranslations" data=["text_orig" => {$ptype->getName(false)|escape}]}"
                                    class="tooltip"
                                >
                                    <i class="ui language grey icon"></i>
                                    <span class="sr-only">{_T string="Translate '%s'" pattern="/%s/" replace=$ptype->getName()}</span>
                                </a>
                                </a>
                {if $ptype->isSystemType()}
                                <i class="ui icon">&nbsp;</i>
                {else}
                                <a
                                    href="{path_for name="removePaymentType" data=["id" => $ptype->id]}"
                                    class="delete tooltip"
                                >
                                    <i class="ui trash red icon"></i>
                                    <span class="sr-only">{_T string="Delete '%s' payment type" pattern="/%s/" replace=$ptype->getName()}</span>
                                </a>
                {/if}
                            </td>
                        </tr>
            {/foreach}
                    </tbody>
                </table>
            {include file="forms_types/csrf.tpl"}
        </form>
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            $(function() {
                {include file="js_removal.tpl"}
            });
        </script>
{/block}
