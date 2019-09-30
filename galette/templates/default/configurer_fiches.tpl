{extends file="page.tpl"}
{block name="content"}
    <section class="tabbed">
        <div id="configfiches_tabs">
            <a
                id="addfield"
                href="{path_for name="addDynamicField" data=["form_name" => $form_name]}"
                class="ui compact icon button tab-button tooltip"
                data-html="{_T string="Add" escape="js"}"
            >
                <i class="plus square icon"></i>
                <span class="sr-only">{_T string="Add"}</span>
            </a>
            <ul>
{foreach from=$all_forms key=key item=form name=formseach}
    {if $form_name eq $key}
        {assign var='activetab' value=$smarty.foreach.formseach.iteration - 1}
    {/if}
                <li{if $form_name eq $key} class="ui-tabs-selected"{/if}><a href="{path_for name="configureDynamicFields" data=["form_name" => $key]}">{$form}</a></li>
{/foreach}
            </ul>
            <div id="ui-tabs-{$form_name}">
                {include file="configurer_fiche_content.tpl"}
            </div>
        </div>
    </section>
{/block}

{block name="javascripts"}
{foreach from=$all_forms key=key item=form name=formseach}
    {if $form_name eq $key}
        {assign var='activetab' value=$smarty.foreach.formseach.iteration}
    {/if}
{/foreach}

        <script type="text/javascript">
            var _form_name;
            $('#addfield').click(function(e){
                e.preventDefault();
                var _this = $(this);
                var _href = '{path_for name="addDynamicField" data=["form_name" => "FORM"]}'.replace(/FORM/, _form_name)

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
                        alert("{_T string="An error occurred :(" escape="js"}");
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

                    console.log(ui.ajaxSettings.url)
                    if ( ui.ajaxSettings.url == '{path_for name="configureDynamicFields" data=["form_name" => $form_name]}'
                        ||  ui.ajaxSettings.url == '{path_for name="configureDynamicFields"}'
                    ) {
                        var _current = $('#ui-tabs-{$form_name}');
                        if (_current) {
                            $('#'+ui.panel[0].id).append(_current)
                        }
                        return false; //avoid reloading first tab onload
                    }

                    var _dimmer = $('<div id="jsloader" class="ui active page dimmer"><div class="ui text loader">{_T string="Currently loading..."}</div><p></p></div>');
                    $('body').append(_dimmer);

                    ui.jqXHR.always(function(){
                        $('#jsloader').remove();
                    });

                    ui.jqXHR.fail(function(){
                        alert('{_T string="An error occurred :(" escape="js"}');
                    });
                }
            });
        </script>
{/block}
