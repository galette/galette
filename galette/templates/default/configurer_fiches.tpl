{extends file="page.tpl"}
{block name="content"}
    <section class="tabbed">
        <div id="configfiches_tabs">
            <a class="button notext" id="btnadd_small" href="{path_for name="editDynamicField" data=["form" => $form_name, "action" => {_T string="add" domain="routes"}]}">{_T string="Add"}</a>
            <ul>
{foreach from=$all_forms key=key item=form name=formseach}
    {if $form_name eq $key}
        {assign var='activetab' value=$smarty.foreach.formseach.iteration}
    {/if}
                <li{if $form_name eq $key} class="ui-tabs-selected"{/if}><a href="{path_for name="configureDynamicFields" data=["form" => $key]}">{$form}</a></li>
{/foreach}
            </ul>
            <div id="ui-tabs-{$activetab}">
                {include file="configurer_fiche_content.tpl"}
            </div>
        </div>
    </section>
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            var _form_name;
            $('#btnadd_small').click(function(e){
                e.preventDefault();
                var _this = $(this);
                var _href = '{path_for name="editDynamicField" data=["form" => "FORM", "action" => {_T string="add" domain="routes"}]}'.replace(/FORM/, _form_name)

                $.ajax({
                    url: _href,
                    type: "GET",
                    datatype: 'json',
                    {include file="js_loader.tpl"},
                    success: function(res){
                        var _res = $(res);
                        _res.find('input[type=submit]')
                            .button();

                        _res.find('form').on('submit', function(e) {
                            e.preventDefault();
                        });

                        _res.dialog({
                            width: '40%',
                            modal: true,
                            close: function(event, ui){
                                $(this).dialog('destroy').remove()
                            }
                        });
                    },
                    error: function() {
                        alert("{_T string="An error occured :(" escape="js"}");
                    }
                });
            });

            $('#configfiches_tabs').tabs({
                active: {$activetab-1},
                load: function(event, ui) {
                    $('#configfiches_tabs input:submit, #configfiches_tabs .button, #configfiches_tabs input:reset' ).button();
                },
                beforeLoad: function(event, ui) {
                    _form_name = ui.ajaxSettings.url.split('/');
                    _form_name = _form_name[_form_name.length-1]

                    if ( ui.ajaxSettings.url == '{path_for name="configureDynamicFields" data=["form" => $form_name]}' ) {
                        return false; //avoid reloading first tab onload
                    }

                    var _img = $('<figure id="loading"><p><img src="{base_url}/{$template_subdir}images/loading.png" alt="{_T string="Loading..."}"/><br/>{_T string="Currently loading..."}</p></figure>');
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
{/block}
