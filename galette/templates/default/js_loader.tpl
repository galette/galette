        beforeSend: function() {
            var _dimmer = $('<div id="jsloader" class="ui active page dimmer"><div class="ui text loader">{_T string="Currently loading..." escape="javascript"}</div><p></p></div>');
            $('body').append(_dimmer);
        },
        complete: function() {
            $('#jsloader').remove();
        }

