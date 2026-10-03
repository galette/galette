/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

var _massCheckboxes = function(selector) {
    if (selector === undefined) {
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
