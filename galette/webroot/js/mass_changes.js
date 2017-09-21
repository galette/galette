var _massCheckboxes = function(selector) {
    if (typeof(selector) == 'undefined') {
        selector = '';
    } else {
        selector = selector + ' ';
    }

    $(selector + 'select, ' + selector + 'textarea, ' + selector + 'input:not(.mass_checkbox)')
        .off().on('change', function() {
           $(this).parent().find('.mass_checkbox').prop('checked', true);
        });
}

$(function() {
    _massCheckboxes('#mass_change');
});
