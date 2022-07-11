/*!
 * Copyright © 2022 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Javascript
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

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
