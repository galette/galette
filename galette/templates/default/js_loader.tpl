        beforeSend: function() {ldelim}
            var _img = $('<figure id="loading"><p><img src="{$template_subdir}images/loading.png" alt="{_T string="Loading..."}"/><br/>{_T string="Currently loading..."}</p></figure>');
            $('body').append(_img);
        {rdelim},
        complete: function() {ldelim}
            $('#loading').remove();
        {rdelim}

