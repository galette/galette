{if $part eq "dialog"}
        <div id="telemetry_panel">
            <h3>{_T string="Telemetry data are <strong>anonymous</strong>; nothing about your organization or its members will be sent."}</h3>
            <p>{_T string="Nothing is automated in the process; it's up to you to send (or resend) data."}<br/>{_T string="You can review below the raw data that would be send if you press the 'Send' button."}.<br/>{_T string="Also note tha all data is sent over a <strong>HTTPS secured connection</strong>."}</p>
            <div class="tdata">
            </div>
        </div>
{/if}
{if $part eq "jsdialog"}
                $('#telemetry_panel').dialog({
                    title: '{_T string="Send telemetry informations" escape="js"}',
                    buttons: {
                        '{_T string="Send" escape="js"}': function() {
                            $.ajax({
                                url:  '{path_for name="telemetrySend"}',
                                method: 'POST',
                                {include file="js_loader.tpl"},
                                success: function(data) {
                                    if (data.success) {
                                        $('#telemetry_panel').dialog('close');
    {if isset($orig) and $orig eq "desktop"}
                                        $('#telemetry').remove();
                                        if ($('#share a').length == 0) {
                                            $('#share').remove();
                                        }
    {/if}
    {if isset($renew_telemetry)}
                                        $('#renewbox').remove();
                                        $.cookie(
                                            'renew_telemetry',
                                            1,
                                            { expires: 365 }
                                        );
    {/if}
                                    }
                                    alert(data.message);
                                },
                                error: function() {
                                    alert("{_T string="An error occured sending telemetry informations :(" escape="js"}");
                                }
                            });
                        },
                        '{_T string="Cancel" escape="js"}': function() {
                            $(this).dialog('close');
                        }
                    },
                    maxHeight: $(window).height(),
                    open: function(event, ui) {
                        $(this).dialog('option', 'maxHeight', $(window).height());
                        $(this).parent().prev('.ui-widget-overlay');
                    },
                    draggable: true,
                    modal: true,
                    resizable: true,
                    width: ($(window).width() > 767 ? '50%' : '100%'),
                    autoOpen: false,
                    close: function(){
                        $(this).find('.tdata').empty();
                    }
                });

                $('#telemetry').on('click', function(e) {
                    e.preventDefault();

                    $.ajax({
                        url:  '{path_for name="telemetryInfos"}',
                        success: function(data) {
                            $('#telemetry_panel .tdata').append(data);
                            $('#telemetry_panel').dialog('open');
                        }

                    });
                });
    {if isset($renew_telemetry)}
            $('#norenew').on('click', function() {
                $.cookie(
                    'renew_telemetry',
                    1,
                    { expires: 365 }
                );
                window.location.reload();
            });
            $('#renewlater').on('click', function() {
                $.cookie(
                    'renew_telemetry',
                    1,
                    { expires: 182 }
                );
                window.location.reload();
            });
    {/if}
{/if}
{if $part eq "jsregister"}
            $('#register').on('click', function(e) {
                $.ajax({
                    url:  '{path_for name="setRegistered"}',
                    success: function(data) {
    {if isset($orig) and $orig eq "desktop"}
                        $('#register').remove();
                        if ($('#share a').length == 0) {
                            $('#share').remove();
                        }
    {/if}
                        alert(data.message);
                    }
                });
            });
{/if}
