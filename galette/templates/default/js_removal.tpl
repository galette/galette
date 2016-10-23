        //handle removals
        $('.delete').on('click', function(event) {
            event.preventDefault();
            var _this = $(this);
            var _href = _this.attr('href');

            $.ajax({
                url: _href,
                type: "GET",
                data: {
                    ajax: true,
                },
                datatype: 'json',
                {include file="js_loader.tpl"},
                success: function(res){
                    var _res = $(res);
                    _res.find('#btncancel')
                        .button()
                        .on('click', function(e) {
                            e.preventDefault();
                            _res.dialog('close');
                        });

                    _res.find('input[type=submit]')
                        .button();

                    _res.find('form').on('submit', function(e) {
                        e.preventDefault();
                        var _form = $(this);
                        var _data = _form.serialize();
                        $.ajax({
                            url: _form.attr('action'),
                            type: "POST",
                            data: _data,
                            datatype: 'json',
                            {include file="js_loader.tpl"},
                            success: function(res){
                                if (res.success) {
                                    window.location.href = _form.find('input[name=redirect_uri]').val();
                                } else {
                                    $.ajax({
                                        url: '{path_for name="ajaxMessages"}',
                                        method: "GET",
                                        success: function (message) {
                                            $('#asso_name').after(message);
                                        }
                                    });
                                }
                            },
                            error: function() {
                                alert("{_T string="An error occured :(" escape="js"}");
                            }
                        });
                    });

                    $('body').append(_res);

                    _res.dialog({
                        width: '25em',
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
