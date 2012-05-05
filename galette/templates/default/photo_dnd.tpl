    {if $member->id}
                //Photo dnd
                // Check if window.FileReader exists to make
                // sure the browser supports file uploads
                if ( typeof(window.FileReader) ) {ldelim}
                    var _dz = $('#photo_adh');

                    // Add a nice drag effect
                    _dz[0].ondragover = function() {ldelim}
                        _dz.addClass('dndhover');
                        return false;
                    {rdelim};

                    // Remove the drag effect when stopping our drag
                    _dz[0].ondragend = function() {ldelim}
                        _dz.removeClass('dndhover');
                        return false;
                    {rdelim};

                    // The drop event handles the file sending
                    _dz[0].ondrop = function(event) {ldelim}
                        // Stop the browser from opening the file in the window
                        event.preventDefault();
                        _dz.removeClass('dndhover');

                        var file = event.dataTransfer.files[0];
                        var reader = new FileReader();
                        reader.readAsDataURL(file);

                        reader.onload = function(evt) {ldelim}
                            $.ajax({ldelim}
                                    type: 'POST',
                                    dataType: 'json',
                                    url : 'ajax_photo.php',
                                    data: {ldelim}
                                        member_id: {$member->id},
                                        filename: file.name,
                                        filesize: file.size,
                                        file: evt.target.result
                                    {rdelim},
                                    {include file="js_loader.tpl"},
                                    success: function(res){ldelim}
                                        if ( res.result == true ) {ldelim}
                                            d = new Date();
                                            var _photo = $('#photo_adh');
                                            _photo.removeAttr('width').removeAttr('height');
                                            _photo.attr('src', $('#photo_adh')[0].src + '&' + d.getTime());
                                            alert("{_T string="Member photo has been changed." escape="js"}");
                                        {rdelim} else {ldelim}
                                            alert(res.message);
                                        {rdelim}
                                    {rdelim},
                                error: function() {ldelim}
                                    alert("{_T string="An error occured sending photo :(" escape="js"}");
                                {rdelim}
                            {rdelim});
                        {rdelim}
                    {rdelim}
                {rdelim}
    {/if}
