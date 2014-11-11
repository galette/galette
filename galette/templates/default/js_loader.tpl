        beforeSend: function() {
            var _img = $('<figure id="loading"><p><img src="{$galette_base_path}{$template_subdir}images/loading.png" alt="{_T string="Loading..."}"/><br/>{_T string="Currently loading..."}</p></figure>');
            $('body').append(_img);
        },
        complete: function() {
            $('#loading').remove();
        }

