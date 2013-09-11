        <form action="configurer_fiches.php" method="post" enctype="multipart/form-data" class="tabbed">
            <div id="addfield" class="cssform">
                <p>
                    <label for="field_name" class="bline">{_T string="Field name"}</label>
                    <input size="40" type="text" name="field_name" id="field_name"/>
                </p>
                <p>
                    <label for="field_perm" class="bline">{_T string="Visibility"}</label>
                    <select name="field_perm" id="field_perm">
                        {html_options options=$perm_names selected="0"}
                    </select>
                </p>
                <p>
                    <label for="field_type" class="bline">{_T string="Type"}</label>
                    <select name="field_type" id="field_type">
                        {html_options options=$field_type_names selected="0"}
                    </select>
                </p>
                <p>
                    <label for="field_required" class="bline">{_T string="Required"}</label>
                    <select name="field_required" id="field_required">
                        <option value="0">{_T string="No"}</option>
                        <option value="1">{_T string="Yes"}</option>
                    </select>
                </p>
                <div class="center">
                    <input type="submit" name="valid" id="btnadd" value="{_T string="Add"}"/>
                    <input type="hidden" name="form" id="formname" value="{$form_name}"/>
                </div>
            </div>

        <div id="configfiches_tabs">
        <ul>
{foreach from=$all_forms key=key item=form name=formseach}
    {if $form_name eq $key}
        {assign var='activetab' value=$smarty.foreach.formseach.iteration}
    {/if}
            <li{if $form_name eq $key} class="ui-tabs-selected"{/if}><a href="?form={$key}">{$form}</a></li>
{/foreach}
        </ul>
        <div id="ui-tabs-{$activetab}">
            {include file="configurer_fiche_content.tpl"}
        </div>
        </div>
        </form>
        <script type="text/javascript">
            $('#configfiches_tabs').append('<a class="button notext" id="btnadd_small">{_T string="Add"}</a>');
            var _dialogform = $('<form id="dialogform" action="configurer_fiches.php" method="post" title="{_T string="Add new dynamic field"}"">');
            _dialogform.append($('#addfield'));
            _dialogform.dialog({
                autoOpen: false,
                modal: true,
                hide: 'fold',
                width: '40%'
            }).dialog('close');

            $('#btnadd_small').click(function(){
                $('#dialogform').dialog('open');
                return false;
            });

            $('#configfiches_tabs > ul > li > a').each(function(){
                $(this).attr('href', $(this).attr('href')  + '&ajax=true');
            });

            $('#configfiches_tabs').tabs({
                load: function(event, ui) {
                    $('#configfiches_tabs input:submit, #configfiches_tabs .button, #configfiches_tabs input:reset' ).button();
                },
                beforeLoad: function(event, ui) {
                    var _reg = /\?form=(.*)&ajax=true/g;
                    $('#formname').val(_reg.exec(ui.ajaxSettings.url)[1]);
                    if ( ui.ajaxSettings.url.match(/\?form={$form_name}.*/) ) {
                        return false; //avoid reloading first tab onload
                    }

                    var _img = $('<figure id="loading"><p><img src="{$template_subdir}images/loading.png" alt="{_T string="Loading..."}"/><br/>{_T string="Currently loading..."}</p></figure>');
                    $('body').append(_img);

                    ui.jqXHR.complete(function(){
                        $('#loading').remove();
                    });

                    ui.jqXHR.error(function(){
                        alert('{_T string="An error occured :("|escape:"js"}');
                    });
                }
            });
        </script>
