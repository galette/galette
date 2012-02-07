    <table class="listing">
        <!--<caption>{_T string="Active plugins"}</caption>-->
        <thead>
            <tr>
                <th class="listing">{_T string="Name"}</th>
                <th class="listing">{_T string="Description"}</th>
                <th class="listing">{_T string="Author"}</th>
                <th class="listing">{_T string="Version"}</th>
                <th class="listing actions_row"></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th colspan="5" class="center"><strong>{_T string="Active plugins"}</strong></th>
            </tr>
{foreach from=$plugins_list key=name item=plugin}
            <tr>
                <td>{$plugin.name} ({$name})</td>
                <td>{$plugin.desc}</td>
                <td>{$plugin.author}</td>
                <td>{$plugin.version}</td>
                <td class="nowrap">
                    <a class="toggleActivation" href="?deactivate={$name}" title="{_T string="Click here to deactivate plugin '%name'" pattern="/%name/" replace=$plugin.name}">
                        <img src="{$template_subdir}images/icon-on.png" alt="{_T string="Disable plugin"}"/>
                    </a>
    {if $plugins->needsDatabase($name)}
                    <a href="ajax_plugins_initdb.php?plugid={$name}" class="initdb" id="initdb_{$name}" title="{_T string="Initialize '%name' database" pattern="/%name/" replace=$plugin.name}">
                        <img src="{$template_subdir}images/icon-db.png" alt="{_T string="Initialize database"}" width="16" height="16"/>
                    </a>
    {else}
                    <img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
    {/if}
                </td>
            </tr>
{foreachelse}
            <tr>
                <td colspan="4">{_T string="No active plugin."}</td>
            </tr>
{/foreach}
            <tr>
                <th colspan="5" class="center"><strong>{_T string="Inactive plugins"}</strong></th>
            </tr>
{foreach from=$plugins_disabled_list key=name item=plugin}
            <tr>
                <td colspan="4">{$name}</td>
                <td>
                    <a class="toggleActivation" href="?activate={$name}" title="{_T string="Click here to activate plugin '%name'" pattern="/%name/" replace=$name}">
                        <img src="{$template_subdir}images/icon-off.png" alt="{_T string="Enable plugin"}"/>
                    </a>
                    <img src="{$template_subdir}images/icon-empty.png" alt="" width="16" height="16"/>
                </td>
            </tr>
{foreachelse}
            <tr>
                <td colspan="4">{_T string="No inactive plugin."}</td>
            </tr>
{/foreach}
        </tbody>
    </table>

    <script type="text/javascript">
        $(function() {ldelim}
{if $GALETTE_MODE eq 'DEMO'}
            $('.initdb, a.toggleActivation').click(function(){ldelim}
                alert('{_T string="Application runs under demo mode. This functionnality is not enabled, sorry." escape="js"}');
                return false;
            {rdelim});
{else}
            var _initdb_dialog = function(res, _plugin){ldelim}
                var _title = '{_T string="Plugin database initialization: %name" escape="js"}';
                var _el = $('<div id="initdb" title="' + _title.replace('%name', _plugin) + '"> </div>');
                _el.appendTo('body').dialog({ldelim}
                    modal: true,
                    hide: 'fold',
                    width: '80%',
                    height: 500,
                    close: function(event, ui){ldelim}
                        _el.remove();
                    {rdelim}
                {rdelim});
                _initdb_bindings(res);
            {rdelim};
            var _initdb_bindings = function(res){ldelim}
                $('#initdb').empty().append(res);
                $('#initdb input:submit, #initdb .button, #initdb input:reset' ).button();
                _messagesEffects();
                $('#btnback').click(function(){ldelim}
                    $('#initdb').dialog('close');
                {rdelim});
                $("#plugins_initdb_form").submit(function(event) {ldelim}
                    /* stop form from submitting normally */
                    event.preventDefault();

                    var $form = $(this);
                    var _url = $form.attr('action');

                    var _dataString = $form.serialize();
                    _dataString += '&ajax=true';

                    $.ajax({ldelim}
                        url: _url,
                        type: "POST",
                        data: _dataString,
                        {include file="js_loader.tpl"},
                        success: function(res){ldelim}
                            _initdb_bindings(res);
                        {rdelim},
                        error: function() {ldelim}
                            alert("{_T string="An error occured displaying plugin database initialization interface :(" escape="js"}");
                        {rdelim}
                    {rdelim});
                {rdelim});
            {rdelim};

            $('.initdb').click(function(){ldelim}
                var _plugin = this.id.substring(7);

                $.ajax({ldelim}
                    url: 'ajax_plugins_initdb.php',
                    type: "POST",
                    data: {ldelim}ajax: true, plugid: _plugin{rdelim},
                    {include file="js_loader.tpl"},
                    success: function(res){ldelim}
                        _initdb_dialog(res, _plugin);
                    {rdelim},
                    error: function() {ldelim}
                        alert("{_T string="An error occured displaying plugin database initialization interface :(" escape="js"}");
                    {rdelim}
                {rdelim});
                return false;
            {rdelim})
{/if}
        {rdelim});
    </script>
