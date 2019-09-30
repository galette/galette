{extends file="page.tpl"}
{block name="content"}
    <table class="listing ui celled table">
        <!--<caption>{_T string="Active plugins"}</caption>-->
        <thead>
            <tr>
                <th class="listing">{_T string="Name"}</th>
                <th class="listing">{_T string="Description"}</th>
                <th class="listing">{_T string="Author"}</th>
                <th class="listing">{_T string="Version"}</th>
                <th class="listing">{_T string="Release date"}</th>
                <th class="listing actions_row"></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th colspan="5" class="bgfree center"><strong>{_T string="Active plugins"}</strong></th>
            </tr>
{foreach from=$plugins_list key=name item=plugin name=allplugins}
            <tr class="{if $smarty.foreach.allplugins.iteration % 2 eq 0}even{else}odd{/if}">
                <td data-scope="row"><a href="{path_for name=$plugin.route|cat:"Info" data=["plugin" => $name]}">{$plugin.name} ({$name})</a></td>
                <td data-title="{_T string="Description"}">{$plugin.desc}</td>
                <td data-title="{_T string="Author"}">{$plugin.author}</td>
                <td data-title="{_T string="Version"}">{$plugin.version}</td>
                <td data-title="{_T string="Release date"}">{$plugin.date}</td>
                <td class="nowrap center actions_row">
                    <a
                        href="{path_for name="pluginsActivation" data=["action" => "deactivate", "module_id" => $name]}"
                        class="toggleActivation tooltip use"
                    >
                        <i class="ui toggle on red icon"></i>
                        <span class="sr-only">{_T string="Click here to deactivate plugin '%name'" pattern="/%name/" replace=$plugin.name}</span>
                    </a>
    {if $plugins->needsDatabase($name)}
                    <a
                        href="{path_for name="pluginInitDb" data=["id" => $name]}"
                        id="initdb_{$name}"
                        class="initdb action tooltip"
                    >
                        <i class="ui database icon"></i>
                        <span class="sr-only">{_T string="Initialize '%name' database" pattern="/%name/" replace=$plugin.name}</span>
                    </a>
    {else}
                    <img src="{base_url}/{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
    {/if}
                </td>
            </tr>
{foreachelse}
            <tr>
                <td colspan="5">{_T string="No active plugin."}</td>
            </tr>
{/foreach}
            <tr>
                <th colspan="5" class="bgfree center"><strong>{_T string="Inactive plugins"}</strong></th>
            </tr>
            <thead>
            <tr>
                <th class="listing">{_T string="Name"}</th>
                <th class="listing" colspan="4">{_T string="Cause"}</th>
                <th class="listing actions_row"></th>
            </tr>
            </thead>
{foreach from=$plugins_disabled_list key=name item=plugin}
            <tr>
                <td data-scope="row">{$name}</td>
                <td data-title="{_T string="Cause"}" colspan="4">
                    {if $plugin.cause eq constant('Galette\Core\Plugins::DISABLED_MISS')}
                        {_T string="A required file is missing"}
                    {elseif $plugin.cause eq constant('Galette\Core\Plugins::DISABLED_COMPAT')}
                        {_T string="Incompatible with current version"}
                    {elseif $plugin.cause eq constant('Galette\Core\Plugins::DISABLED_EXPLICIT')}
                        {_T string="Explicitely disabled"}
                    {else}
                        {_T string="Unknown"}
                    {/if}
                </td>
                <td class="nowrap center actions_row">
                    <a
                        href="{path_for name="pluginsActivation" data=["action" => "activate", "module_id" => $name]}"
                        class="toggleActivation tooltip delete"
                    >
                        <i class="ui toggle on icon"></i>
                        <span class="sr-only">{_T string="Activate plugin '%name'" pattern="/%name/" replace=$name}</span>
                    </a>
                    <i class="ui icon">&nbsp;</i>
                </td>
            </tr>
{foreachelse}
            <tr>
                <td colspan="5">{_T string="No inactive plugin."}</td>
            </tr>
{/foreach}
        </tbody>
    </table>
{/block}

{block name="javascripts"}
    <script type="text/javascript">
        $(function() {
    {if $GALETTE_MODE eq 'DEMO'}
            $('.initdb, a.toggleActivation').click(function(){
                alert('{_T string="Application runs under demo mode. This functionnality is not enabled, sorry." escape="js"}');
                return false;
            });
    {else}
            var _initdb_dialog = function(res, _plugin){
                var _title = '{_T string="Plugin database initialization: %name" escape="js"}';
                var _el = $('<div id="initdb" title="' + _title.replace('%name', _plugin) + '"> </div>');
                _el.appendTo('body').dialog({
                    modal: true,
                    hide: 'fold',
                    width: '80%',
                    height: 500,
                    create: function (event, ui) {
                        if ($(window ).width() < 767) {
                            $(this).dialog('option', {
                                    'width': '95%',
                                    'draggable': false
                            });
                        }
                    },
                    close: function(event, ui){
                        _el.remove();
                    }
                });
                _initdb_bindings(res);
            };
            var _initdb_bindings = function(res){
                $('#initdb').empty().append(res);
                $('#initdb input:submit, #initdb .button, #initdb input:reset' ).button();
                _messagesEffects();
                $('#btnback').click(function(){
                    $('#initdb').dialog('close');
                });
                $("#plugins_initdb_form").submit(function(event) {
                    /* stop form from submitting normally */
                    event.preventDefault();

                    var $form = $(this);
                    var _url = $form.attr('action');

                    var _dataString = $form.serialize();
                    _dataString += '&ajax=true';

                    $.ajax({
                        url: _url,
                        type: "POST",
                        data: _dataString,
                        {include file="js_loader.tpl"},
                        success: function(res){
                            _initdb_bindings(res);
                        },
                        error: function() {
                            alert("{_T string="An error occurred displaying plugin database initialization interface :(" escape="js"}");
                        }
                    });
                });
            };

            $('.initdb').click(function(){
                var _plugin = this.id.substring(7);
                var _url = $(this).attr('href');

                $.ajax({
                    url: _url,
                    type: "GET",
                    {include file="js_loader.tpl"},
                    success: function(res){
                        _initdb_dialog(res, _plugin);
                    },
                    error: function() {
                        alert("{_T string="An error occurred displaying plugin database initialization interface :(" escape="js"}");
                    }
                });
                return false;
            })
    {/if}
        });
    </script>
{/block}
