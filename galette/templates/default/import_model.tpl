<div id="model_tabs" class="tabbed">
    <ul>
        <li><a href="#current">{_T string="Current model"}</a></li>
        <li><a href="#change">{_T string="Change model"}</a></li>
    </ul>
    <div id="current">
        <table class="listing">
            <caption>
                {if $defaults_loaded}
                    {_T string="Default fields"}
                {else}
                    {_T string="Model parameted on %date" pattern="/%date/" replace=$model->getCreationDate()}
                {/if}
            </caption>
            <thead>
                <tr>
                    <th>{_T string="Field"}</th>
                </tr>
            </thead>
            <tbody>
        {foreach item=field from=$fields name=fields_list}
                <tr class="{if $smarty.foreach.fields_list.iteration % 2 eq 0}even{else}odd{/if}">
                    <td>{$members_fields[$field]['label']|replace:':':''}</td>
                </tr>
        {/foreach}
        </table>
        <div class="button-container">
            <a id="memberslist" class="button" href="import_model.php?generate=true">{_T string="Generate empty CSV file"}</a>
            {if !$defaults_loaded}
            <a id="delete" class="button" href="import_model.php?remove=true">{_T string="Remove model and back to defaults"}</a>
            {/if}
        </div>
    </div>
    <div id="change">
        <form action="import_model.php" method="POST">
        <table class="listing">
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
            <input type="submit" name="upload" value="{_T string="Store new model"}"/>
        </div>
        </form>
    </div>
</div>
<p class="center">
    <a class="button" id="btnback" href="import.php">{_T string="Go back to import page"}</a>
</p>

<script type="text/javascript">
    $(function(){
        $('#model_tabs').tabs();

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
