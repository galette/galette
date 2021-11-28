        beforeSend: function() {
            var _img = $('<figure id="loading"><p><img src="{base_url}/{$template_subdir}images/loading.png" alt="{_T string="Loading..." escape="javascript"}"/><br/>{_T string="Currently loading..." escape="javascript"}</p></figure>');
            $('body').append(_img);
        },
        complete: function() {
            $('#loading').remove();
        }

