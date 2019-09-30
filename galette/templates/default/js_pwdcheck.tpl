{if !isset($extra_data)}
    {assign var="extra_data" value=""}
{/if}
$('{$selector}').parent('p').append($('<meter max="4" class="password-strength-meter"></meter><span class="password-strength-text"></span>'));
$('{$selector}').on('keyup', function() {
    var _this = $(this);
    $.ajax({
        url: '{path_for name="checkPassword"}',
        type: 'POST',
        data: {
            value: $('{$selector}').val(),
            {$extra_data}
        },
        {include file="js_loader.tpl"},
        success: function(res) {
            var _p = _this.parent('p');
            var _meter = _p.find('.password-strength-meter');

            _meter.val(res.score);
            var _txt = _p.find('span.password-strength-text');

            //reset
            _p.find('.passtips').remove();
            _txt.attr('class', 'password-strength-text');
            _txt.html('');
            _meter.attr('class', 'password-strength-meter');
            _meter.attr('title', '');

            if (res.valid) {
                _txt.append($('<i class="ui check circle green icon"></i> <span>{_T string="Password is valid :)" escape="js"}</span>'));
                _txt.addClass('use');
            } else {
                _txt.append($('<i class="ui times circle red icon"></i> <span>{_T string="Password is not valid!" escape="js"}</span>'));

                _txt.append(' (');
                for (i = 0; i < res.errors.length; i++) {
                    if (i > 0) {
                        _txt.append(', ');
                    }
                    _txt.append(res.errors[i]);
                }
                _txt.append(')');
                _txt.addClass('delete');
            }

            if (res.warnings) {
                _meter.addClass('tooltip');
                var _tip = $('<span class="passtips tip"></span>');
                _tip.hide();
                for (i = 0; i < res.warnings.length; i++) {
                    if (i > 0) {
                        _tip.append('<br/>');
                    }
                    _tip.append(res.warnings[i]);
                }
                _meter.after(_tip);
            }
        },
        error: function () {
            alert('{_T string="An error occured checking password :(" escape="js"}');
        }
    });

});

