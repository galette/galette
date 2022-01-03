{extends file="page.tpl"}

{block name="content"}
        <form action="{path_for name="searches"}" method="post" enctype="multipart/form-data">
                <table class="listing ui celled table">
                    <thead>
                        <tr>
                            <th class="id_row">#</th>
                            <th>{_T string="Creation date"}</th>
                            <th>{_T string="Name"}</th>
                            <th>{_T string="Parameters"}</th>
                            <th>{_T string="Actions"}</th>
                        </tr>
                    </thead>
                    <tbody>
{foreach from=$searches item=search name=allsearches}
                        <tr class="{if $smarty.foreach.allsearches.iteration % 2 eq 0}even{else}odd{/if}">
                            <td data-scope="row">
    {if $preferences->pref_show_id}
                                {$search->id}
    {else}
                                {$smarty.foreach.allsearches.iteration}
    {/if}
                            </td>
                            <td class="left" data-title="{_T string="Creation date"}">{$search->creation_date}</td>
                            <td class="left" data-title="{_T string="Creation date"}">{$search->name|default:"-"}</td>
                            <td class="left" data-title="{_T string="Search parameters"}">
                                <a href="#" class="searchparams" title="{_T string="Show parameters"}">
                                    <i class="ui info circle primary icon"></i>
                                    <span class="sr-only">
        {foreach $search->sparameters key=key item=parameter name=listparameters}
                                    <strong>{$key}:</strong> {$parameter}{if not $parameter@last}<br />{/if}
        {/foreach}
                                    </span>
                                </a>
                            </td>
                            <td class="center actions_row">
                                <a
                                    href="{path_for name="loadSearch" data=["id" => $search->id]}"
                                    class="tooltip"
                                >
                                    <i class="ui search icon"></i>
                                    <span class="sr-only">{_T string="Load saved search"}</span>
                                </a>

                                <a
                                    href="{path_for name="removeSearch" data=["id" => $search->id]}"
                                    class="delete tooltip"
                                >
                                    <i class="ui trash red icon"></i>
                                    <span class="sr-only">{_T string="Delete saved search"}</span>
                                </a>
                            </td>
                        </tr>
{foreachelse}
                <tr><td colspan="5" class="emptylist">{_T string="no search"}</td></tr>
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

                if ( _shq = $('.searchparams') ) {
                    _shq.click(function(e){
                        e.preventDefault();
                        return false;
                    });
                }

            });
        </script>
{/block}
