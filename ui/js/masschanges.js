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

    //fields may be wrapped (dropdowns, calendars, inputs with icons):
    //look for the closest ancestor holding a mass checkbox
    var _container = function(field) {
        return $(field).parents().filter(function() {
            return $(this).find('.mass_checkbox').length > 0;
        }).first();
    };

    //state of all fields related to a mass checkbox (a switch, several radios, a dropdown and its hidden input...)
    var _state = function(container) {
        return container.find('select, textarea, input').not('.mass_checkbox').map(function() {
            if (this.type === 'checkbox' || this.type === 'radio') {
                return this.checked ? '1' : '0';
            }
            return JSON.stringify($(this).val());
        }).get().join('|');
    };

    var _fields = $(selector + 'select, ' + selector + 'textarea, ' + selector + 'input:not(.mass_checkbox)');

    _fields.each(function() {
        const _parent = _container(this);
        if (_parent.length === 1 && _parent.data('mass-initial') === undefined) {
            _parent.data('mass-initial', _state(_parent));
        }
    });

    //a box checked by the user is kept: applying the initial value (an empty one for example) is legit
    $(selector + '.mass_checkbox').off('change.masschanges').on('change.masschanges', function() {
        $(this).data('mass-manual', this.checked);
    });

    _fields.off().on('change', function() {
        const _parent = _container(this);
        const _checkbox = _parent.find('.mass_checkbox');
        if (_checkbox.length === 1) {
            if (_state(_parent) !== _parent.data('mass-initial')) {
                _checkbox.prop('checked', true);
            } else if (!_checkbox.data('mass-manual')) {
                //going back to the initial value is not a change
                _checkbox.prop('checked', false);
            }
        }
    });
}

$(function() {
    _massCheckboxes('#mass_change');
});
