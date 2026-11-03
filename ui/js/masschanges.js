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
            //fields may be wrapped (dropdowns, calendars, inputs with icons):
            //look for the closest ancestor holding a mass checkbox
            const _checkbox = $(this).parents().filter(function() {
                return $(this).find('.mass_checkbox').length > 0;
            }).first().find('.mass_checkbox');
            if (_checkbox.length === 1) {
                _checkbox.prop('checked', true);
            }
        });
}

$(function() {
    _massCheckboxes('#mass_change');
});
