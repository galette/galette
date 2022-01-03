{extends file="page.tpl"}

{block name="content"}
<div class="ui two item top attached stackable tabs menu tabbed">
    <a class="item active" data-tab="current">{_T string="Current model"}</a>
    <a class="item" data-tab="change">{_T string="Change model"}</a>
</div>
<div class="ui bottom attached active tab segment" data-tab="current">
    <table class="listing ui celled table">
        <div class="ui small header">
            {if $defaults_loaded}
                {_T string="Default fields"}
            {else}
                {_T string="Model parameted on %date" pattern="/%date/" replace=$model->getCreationDate()}
            {/if}
        </div>
        <thead>
            <tr>
                <th>{_T string="Field"}</th>
            </tr>
        </thead>
        <tbody>
    {foreach item=field from=$fields name=fields_list}
            <tr class="{if $smarty.foreach.fields_list.iteration % 2 eq 0}even{else}odd{/if}">
        {if !isset($members_fields[$field])}
                <td>{_T string="Missing field '%field'" pattern="/%field/" replace=$field}</td>
        {else}
                <td>{$members_fields[$field]['label']|replace:':':''}</td>
        {/if}
            </tr>
    {/foreach}
    </table>
    <div class="button-container">
        <a class="ui labeled icon primary button" href="{path_for name="getImportModel"}">
            <i class="file csv icon" aria-hidden="true"></i>
            {_T string="Generate empty CSV file"}
        </a>
        {if !$defaults_loaded}
        <a
            id="delete"
            class="ui labeled icon button delete tooltip"
            href="{path_for name="importModel"}?remove=true"
            title="{_T string="Remove model and back to defaults"}"
        >
            <i class="trash icon" aria-hiden="true"></i>
            {_T string="Remove model"}
        </a>
        {/if}
    </div>
</div>
<div class="ui bottom attached tab segment" data-tab="change">
    <form action="{path_for name="storeImportModel"}" method="POST" class="ui form">
        <table class="listing ui celled table">
            <thead>
                <tr>
                    <th></th>
                    <th>{_T string="Field"}</th>
                </tr>
            </thead>
            <tbody>
        {foreach item=field from=$members_fields name=members_fields_list key=k}
                <tr class="{if $smarty.foreach.members_fields_list.iteration % 2 eq 0}even{else}odd{/if}">
                    <td>
                        <input type="checkbox" name="fields[]" id="field_{$k}" value="{$k}"{if in_array($k, $fields)} checked="checked"{/if}/>
                    </td>
                    <td>
                        <label for="field_{$k}">{$field['label']|replace:':':''}</label>
                    </td>
                </tr>
        {/foreach}
        </table>
        <div class="button-container">
            <button type="submit" name="upload" class="ui labeled icon primary button action">
                <i class="save icon" aria-hidden="true"></i>
                {_T string="Store new model"}
            </button>
            {include file="forms_types/csrf.tpl"}
        </div>
    </form>
</div>
<div class="button-container">
    <a
        class="ui labeled icon button"
        href="{path_for name="import"}"
    >
        <i class="arrow left icon" aria-hidden="true"></i>
        {_T string="Go back to import page"}
    </a>
</div>
{/block}

{block name="javascripts"}
<script type="text/javascript">
    $(function(){
        //$('#model_tabs').tabs();

        $('input[type="submit"]').click(function(){
            var _checkeds = $('table.listing').find('input[type=checkbox]:checked').length;
            if ( _checkeds == 0 ) {
                var _el = $('<div id="pleaseselect" title="{_T string="No field selected" escape="js"}">{_T string="Please make sure to select at least one field from the list to perform this action." escape="js"}</div>');
                _el.appendTo('body').dialog({
                    modal: true,
                    buttons: {
                        Ok: function() {
                            $(this).dialog( "close" );
                        }
                    },
                    close: function(event, ui){
                        _el.remove();
                    }
                });
                return false;
            }
        });
    });
</script>
{/block}
