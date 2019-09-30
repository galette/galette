{extends file="page.tpl"}

{block name="content"}
        <div class="tabbed">
        <div id="tabs">
            <ul>
{foreach from=$models item=m name=formodels}
    {if $m->id eq $model->id}
        {assign var='activetab' value=$smarty.foreach.formodels.iteration}
    {/if}
                <li{if $m->id eq $model->id} class="ui-tabs-selected"{/if}><a href="{path_for name="pdfModels" data=["id" => $m->id]}">{$m->name}</a></li>
{/foreach}
            </ul>
            <div id="ui-tabs-{$activetab}">
{include file="gestion_pdf_content.tpl"}
            </div>
        </div>
        </div>
{/block}
<i class="circular inverted primary link icon info tooltip" data-html="{_T string="Enter here a short description for your association, it will be displayed on the index page and into pages' title."}"></i>
{block name="javascripts"}
        <script type="text/javascript">
            _addLegenButton('#tabs');

            $(function(){
                $('#tabs').tabs({
                    active: {$activetab-1},
                    load: function(event, ui) {
                        $('#tabs input:submit, #tabs .button, #tabs input:reset' ).button();
                        _handleLegend($(event.currentTarget).attr('href').replace('{path_for name="pdfModels"}/', ''));
                    },
                    {* Cannot include js_loader.tpl here because we need to use beforeSend specificaly... *}
                    beforeLoad: function(event, ui) {
                        _tab_name = ui.ajaxSettings.url.split('/');
                        _tab_name = _tab_name[_tab_name.length-1];

                        _handleLegend({$model->id});

                        if ( ui.ajaxSettings.url == '{path_for name="pdfModels" data=["id" => $model->id]}'
                             ||  ui.ajaxSettings.url == '{path_for name="pdfModels"}'
                        ) {
                            var _current = $('#ui-tabs-{$activetab}');
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
            });
        </script>
{/block}
