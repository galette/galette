        <div class="tabbed">
        <div id="tabs">
            <ul>
{foreach from=$models item=m name=formodels}
    {if $m->id eq $model->id}
        {assign var='activetab' value=$smarty.foreach.formodels.iteration}
    {/if}
                <li{if $m->id eq $model->id} class="ui-tabs-selected"{/if}><a href="?id={$m->id}">{$m->name}</a></li>
{/foreach}
            </ul>
            <div id="ui-tabs-{$activetab}">
{include file="gestion_pdf_content.tpl"}
            </div>
        </div>
        <p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
        </div>
        <script type="text/javascript">
            $(function(){
                $('#tabs > ul > li > a').each(function(){
                    $(this).attr('href', $(this).attr('href')  + '&ajax=true');
                });

                $('#tabs').tabs({
                    load: function(event, ui) {
                        $('#tabs input:submit, #tabs .button, #tabs input:reset' ).button();
                    },
                    ajaxOptions: {
                        {* Cannot include js_loader.tpl here because we need to use beforeSend specificaly... *}
                        beforeSend: function(xhr, settings) {
                            if ( settings.url.match(/\?id={$model->id}.*/) ) {
                                return false; //avoid reloading first tab onload
                            }
                            var _img = $('<figure id="loading"><p><img src="{$template_subdir}images/loading.png" alt="{_T string="Loading..."}"/><br/>{_T string="Currently loading..."}</p></figure>');
                            $('body').append(_img);
                        },
                        complete: function() {
                            $('#loading').remove();
                        },
                        error: function( xhr, status, index, anchor ) {
                            alert('{_T string="An error occured :("|escape:"js"}');
                        }
                    }
                });
            });
        </script>
