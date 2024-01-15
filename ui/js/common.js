/*!
 * Copyright © 2007-2024 The Galette Team
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
 * @copyright 2007-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-06
 */

/* Fomantic UI components */
var _bindFomanticComponents = function() {
    var
        $sidebar         = $('.ui.sidebar'),
        $dropdown        = $('.ui.dropdown:not(.navigation, .autosubmit, .nochosen), select:not(.nochosen)'),
        $dropdownNav     = $('.ui.dropdown.navigation'),
        $accordion       = $('.ui.accordion'),
        $checkbox        = $('.ui.checkbox, .ui.radio.checkbox'),
        $tabulation      = $('.ui.tabbed .item'),
        $popup           = $('a[title], .tooltip'),
        $infoPopup       = $('i.circular.primary.icon.info.tooltip'),
        $menuPopupRight  = $('.ui.vertical.accordion.menu a[title]'),
        $menuPopupBottom = $('.ui.top.fixed.menu a.item[title]'),
        $menuPopupLeft   = $('.ui.dropdown.right-aligned a[title]')
    ;

    $sidebar.sidebar('attach events', '.toc.item');

    /* Make all dropdowns clickable when js is enabled for UX consistency.
     * Keep them hoverable only when js is disabled.
     */
    $('.simple.dropdown').removeClass('simple');
    $dropdown.dropdown();

    /* Required for keyboard accessibility on dropdowns used in navigation.
     */
    $dropdownNav.dropdown({
        // Set default action : simply open the link selected.
        action: function(text, value, element) {
            location.href = element[0].href;
        }
    });

    $accordion.accordion();

    $checkbox.checkbox();

    $tabulation.tab();

    /* Fomantic UI Tooltips */
    /* Hide all popups when a dropdown is shown. */
    $.fn.dropdown.settings.onShow = function() {
        $('body').popup('hide all');
    };
    /* Hide all popups when an accordion is opened. */
    $.fn.accordion.settings.onOpening = function() {
        $('body').popup('hide all');
    };
    /* Default behaviour for tooltips on links with a title attribute,
     * or other tags with the "tooltip" class.
     * The title (or data-html) attribute is appended to body and removed
     * from DOM after being hidden (inline: false).
     */
    $popup
        .popup({
            variation: 'inverted',
            inline: false,
            addTouchEvents: false,
        })
    ;
    /* Touch events are allowed on info icons popups.
     */
    $infoPopup
        .popup({
            variation: 'inverted',
            inline: false,
            addTouchEvents: true,
        })
    ;
    /* Position right on the main accordion menu.
     */
    $menuPopupRight
        .popup({
            position: 'right center',
            variation: 'inverted',
            delay: {
                show: 300
            },
            addTouchEvents: false,
        })
    ;
    /* Position bottom on the top fixed menu.
     */
    $menuPopupBottom
        .popup({
            position: 'center bottom',
            variation: 'inverted',
            addTouchEvents: false,
        })
    ;
    /* Position left on the top right language dropdown menu.
     */
    $menuPopupLeft
        .popup({
            position: 'left center',
            variation: 'inverted',
            delay: {
                show: 300
            },
            addTouchEvents: false,
        })
    ;
}

/* Required for keyboard navigation accessibility.
 */
var _keyboardNavigation = function() {
    // Accordion menus
    var _folds = document.querySelectorAll('[data-fold^="fold-"]');
    _folds.forEach(item => {
        item.addEventListener('keydown', event => {
            if (event.keyCode == 13) {
                event.target.click();
            }
        })
    });
    // Mobile menu trigger
    var _mobile_menu_trigger = document.querySelector('#top-navbar a.toc.item');
    _mobile_menu_trigger.addEventListener('keydown', event => {
        if (event.keyCode == 13) {
            // Open mobile menu
            event.target.click();
            // Jump to mobile menu
            var url = location.href;
            location.href = "#sidebarmenu";
            history.replaceState(null,null,url);
        }
    });
}

/* Required for keyboard accessibility on simple dropdowns with autosubmit.
 */
var _bindDropdownsAutosubmit = function() {
    $('.ui.dropdown.autosubmit').dropdown({
        action: function(text, value, element) {
            var element = element.parentElement !== undefined ? element : element[0];
            var dropdown = element.closest('.ui.dropdown');
            var form = element.closest('form');
            $(dropdown).dropdown('set value', value);
            $(dropdown).dropdown('hide');
            $(form).trigger('submit');
        }
    });
}

var _bind_check = function(boxelt) {
    if (typeof(boxelt) == 'undefined') {
        boxelt = 'entries_sel'
    }
    var _is_checked = true;
    $('.checkall').click(function(){
        $('table.listing :checkbox[name="' + boxelt + '[]"]').each(function(){
            this.checked = _is_checked;
        });
        _is_checked = !_is_checked;
        return false;
    });
    $('.checkinvert').click(function(){
        var _haschecked = false;
        $('table.listing :checkbox[name="' + boxelt + '[]"]').each(function(){
            if ($(this).is(':checked')) {
                this.checked = false;
            } else {
                this.checked = true;
                _haschecked = true;
            }
        });
        if (!_haschecked) {
            _is_checked = true;
        } else {
            _is_checked = false;
        }
        return false;
    });
};

/* Display tables legends in Fomantic UI modal */
var _bind_legend = function() {
    $('.show_legend').click(function(e){
        $('#legende').modal('show');
    });
}

$(function() {
    $('.nojs').removeClass('nojs').addClass('jsenabled');
    /* Display/enable elements required only when javascript is active */
    $('.jsenabled .jsonly.displaynone').removeClass('displaynone');
    $('.jsenabled .jsonly.disabled').removeClass('disabled');
    $('.jsenabled .jsonly.read-only').removeClass('read-only');
    $('.jsenabled .jsonly.search-dropdown').removeClass('search-dropdown').addClass('search clearable selection dropdown');

    _bindFomanticComponents();

    _bindDropdownsAutosubmit();

    _keyboardNavigation();

    var _back2Top = document.getElementById("back2top");
    document.body.addEventListener('scroll', function() {
        if (document.body.scrollTop > 150 || document.documentElement.scrollTop > 150) {
            _back2Top.style.display = "block";
        } else {
            _back2Top.style.display = "none";
        }
    });
    _back2Top.onclick = function(event){
        event.preventDefault();
        document.body.scrollTop = 0;
        document.documentElement.scrollTop = 0;
    }
});
