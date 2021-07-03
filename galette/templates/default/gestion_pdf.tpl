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

{block name="javascripts"}
        <script type="text/javascript">
            $('#tabs').append('<a id="btnlegend" class="tab-button tooltip action" title="{_T string="Show existing variables"}"><i class="fas fa-info-circle fa-2x"></i> <span class="sr-only">{_T string="Show existing variables" escape="js"}</span></a>');

            var _handleLegend = function(index) {
                $('#legende' + index + ' h1').remove();
                $('#legende' + index).dialog({
                    autoOpen: false,
                    modal: true,
                    hide: 'fold',
                    width: '60em'
                }).dialog('close');

                $('#btnlegend').unbind('click').click(function(){
                    $('#legende' + index).dialog('open');
                        return false;
                });
            };

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

                        var _img = $('<figure id="loading"><p><img src="{base_url}/{$template_subdir}images/loading.png" alt="{_T string="Loading..."}"/><br/>{_T string="Currently loading..."}</p></figure>');
                        $('body').append(_img);

                        ui.jqXHR.always(function(){
                            $('#loading').remove();
                        });

                        ui.jqXHR.fail(function(){
                            alert('{_T string="An error occurred :(" escape="js"}');
                        });
                    }
                });
            });
        </script>
{/block}
